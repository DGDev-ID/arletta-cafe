<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\BalanceLog;
use App\Models\BalanceTopup;
use App\Services\QrislyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BalanceController extends Controller
{
    public function index(Request $request)
    {
        // Pastikan ada record balance, jika belum ada maka buat
        $balance = Balance::firstOrCreate([], ['balance' => 0]);

        // Topup history paginated
        $topupsQuery = $balance->topups()->latest();
        $topups = $topupsQuery->paginate(5, ['*'], 'topup_page');

        // Balance logs dengan filter date range
        $logsQuery = $balance->logs()->latest();

        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        if ($dateFrom) {
            $logsQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $logsQuery->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $logsQuery->paginate(5, ['*'], 'log_page');

        return Inertia::render('balance/Index', [
            'balance' => $balance,
            'topups'  => $topups,
            'logs'    => $logs,
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to'   => $dateTo ?? '',
            ],
        ]);
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:10000'],
        ]);

        $balance = Balance::firstOrCreate([], ['balance' => 0]);

        // Generate unique_amount: tambahkan 3 digit unik random 1-999
        $topupAmount  = (int) $request->amount;

        // Panggil Qrisly untuk generate QRIS
        $qrisly   = new QrislyService();
        $qrisData = $qrisly->generateTopupQris($topupAmount);

        if (! $qrisData) {
            return back()->with('error', 'Gagal membuat QRIS topup. Silakan coba lagi.');
        }

        $historyId = $qrisData['history_id'] ?? null;
        $uniqueAmount = (int) $qrisData['final_amount'] - (int) $topupAmount;
        $totalAmount = (int) $qrisData['final_amount'];

        // Simpan record topup
        $topup = BalanceTopup::create([
            'balance_id'     => $balance->id,
            'topup_amount'   => $topupAmount,
            'unique_amount'  => $uniqueAmount,
            'total_amount'   => $totalAmount,
            'qris_history_id' => $historyId,
            'status'         => 'pending',
        ]);

        return redirect()->route('balance.topup.show', $topup->id);
    }
}
