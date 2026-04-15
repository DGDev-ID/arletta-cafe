<?php

namespace App\Http\Controllers\Dashboard\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashierController extends Controller
{
    public function index(Request $request)
    {
        $cafeId = $request->input('cafe_id');
        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();

        $pendingQuery = Transaction::where('status', 'pending')
            ->with(['cafe', 'table']);

        $inOrderQuery = Transaction::where('status', 'in_order')
            ->with(['cafe', 'table']);

        if ($cafeId) {
            $pendingQuery->where('cafe_id', $cafeId);
            $inOrderQuery->where('cafe_id', $cafeId);
        }

        return Inertia::render('transaction/cashier/Index', [
            'pendingTransactions' => $pendingQuery->latest()->get(),
            'inOrderTransactions' => $inOrderQuery->latest()->get(),
            'cafes' => $cafes,
            'filters' => [
                'cafe_id' => $cafeId ?? '',
            ],
        ]);
    }

    public function show($id)
    {
        $transaction = Transaction::where('status', 'pending')->where('payment_type', 'manual')
             ->with([
                'cafe',
                'table',
                'details.menu.category',
            ])
            ->with([
                'cafe',
                'table',
                'details.menu.category',
            ])
            ->findOrFail($id);

        return Inertia::render('transaction/cashier/Show', [
            'transaction' => $transaction,
        ]);
    }

    public function makeSuccess($id)
    {
        $transaction = Transaction::where('status', 'pending')->where('payment_type', 'manual')->findOrFail($id);
        $transaction->update(['status' => 'in_order']);

        return redirect()
            ->route('transaction.cashier.index')
            ->with('success', 'Transaksi berhasil disetujui dan masuk ke antrian.');
    }

    public function makeFailed($id)
    {
        $transaction = Transaction::where('status', 'pending')->where('payment_type', 'manual')->findOrFail($id);
        $transaction->update(['status' => 'failed']);

        return redirect()
            ->route('transaction.cashier.index')
            ->with('success', 'Transaksi ditolak.');
    }

    public function makeSuccessInOrder($id)
    {
        $transaction = Transaction::where('status', 'in_order')->findOrFail($id);
        $transaction->update(['status' => 'success']);

        return redirect()
            ->route('transaction.cashier.index')
            ->with('success', 'Transaksi berhasil diselesaikan.');
    }

    public function printReceipt($id)
    {
        $transaction = Transaction::where('status', 'in_order')
            ->with([
                'cafe',
                'table',
                'details.menu',
            ])
            ->findOrFail($id);

        return Inertia::render('transaction/cashier/Receipt', [
            'transaction' => $transaction,
        ]);
    }

    public function receiptData($id)
    {
        $transaction = Transaction::whereIn('status', ['in_order', 'success'])
            ->with(['cafe:id,name,address', 'table:id,name', 'details.menu:id,name'])
            ->findOrFail($id);

        return response()->json($transaction);
    }
}
