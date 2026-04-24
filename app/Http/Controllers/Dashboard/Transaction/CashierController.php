<?php

namespace App\Http\Controllers\Dashboard\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashierController extends Controller
{
    public function index(Request $request)
    {
        $cafeId = $request->input('cafe_id');
        $user = auth()->user();

        $allowedCafeIds = null;
        if ($user->hasRole('Admin')) {
            $allowedCafeIds = \App\Models\CafeAdmin::where('user_id', $user->id)->pluck('cafe_id');
            $cafes = MCafe::whereIn('id', $allowedCafeIds)->select('id', 'name')->orderBy('name')->get();
        } elseif ($user->hasRole('Cashier')) {
            $allowedCafeIds = \App\Models\CafeCashier::where('user_id', $user->id)->pluck('cafe_id');
            $cafes = MCafe::whereIn('id', $allowedCafeIds)->select('id', 'name')->orderBy('name')->get();
        } else {
            // Super Admin or Backoffice
            $cafes = MCafe::select('id', 'name')->orderBy('name')->get();
        }

        $pendingQuery = Transaction::where('status', 'pending')
            ->where('payment_type', 'manual')
            ->with(['cafe', 'table']);

        $inOrderQuery = Transaction::where('status', 'in_order')
            ->with(['cafe', 'table']);

        $successQuery = Transaction::where('status', 'success')
            ->where('updated_at', '>=', now()->subHours(26))
            ->with(['cafe', 'table']);

        if ($allowedCafeIds !== null) {
            $pendingQuery->whereIn('cafe_id', $allowedCafeIds);
            $inOrderQuery->whereIn('cafe_id', $allowedCafeIds);
            $successQuery->whereIn('cafe_id', $allowedCafeIds);
        }

        if ($cafeId) {
            // Ensure requested cafeId is allowed if restrictions apply
            if ($allowedCafeIds === null || $allowedCafeIds->contains($cafeId)) {
                $pendingQuery->where('cafe_id', $cafeId);
                $inOrderQuery->where('cafe_id', $cafeId);
                $successQuery->where('cafe_id', $cafeId);
            }
        }

        return Inertia::render('transaction/cashier/Index', [
            'pendingTransactions' => $pendingQuery->latest()->get(),
            'inOrderTransactions' => $inOrderQuery->latest()->get(),
            'successTransactions' => $successQuery->latest()->get(),
            'cafes' => $cafes,
            'filters' => [
                'cafe_id' => $cafeId ?? '',
            ],
        ]);
    }

    public function searchByQRCode($qr_code)
    {
        $transaction = Transaction::where('unique_code', $qr_code)
            ->where('status', 'pending')
            ->where('payment_type', 'manual')
            ->with(['cafe', 'table'])
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'QR Code tidak valid atau transaksi tidak ditemukan.'], 404);
        }

        return response()->json($transaction);
    }

    public function show($id)
    {
        $transaction = Transaction::where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('status', 'pending')->where('payment_type', 'manual');
            })->orWhereIn('status', ['in_order', 'success']);
        })
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
        TransactionService::makeSuccess($transaction);

        return redirect()
            ->route('transaction.cashier.index')
            ->with('success', 'Transaksi berhasil disetujui dan masuk ke antrian.');
    }

    public function makeFailed($id)
    {
        $transaction = Transaction::where('status', 'pending')->where('payment_type', 'manual')->findOrFail($id);
        TransactionService::makeFailed($transaction);

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
        $transaction = Transaction::whereIn('status', ['in_order', 'success'])
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
