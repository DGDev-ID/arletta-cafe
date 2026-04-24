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

    public function bluetoothReceiptData($id)
    {
        $transaction = Transaction::whereIn('status', ['in_order', 'success'])
            ->with(['cafe', 'table', 'details.menu'])
            ->findOrFail($id);

        $a = array();

        $cleanNumber = function ($val) {
            return preg_replace('/[^\d]/', '', number_format($val, 0, ',', '.'));
        };

        $fmtDate = function ($val) {
            return date('d/m/Y H:i:s', strtotime($val));
        };

        $WIDTH = 32;
        $lineStr = str_repeat('-', $WIDTH);

        $padRight = function ($left, $right) use ($WIDTH) {
            $space = $WIDTH - (strlen($left) + strlen($right));
            return $left . str_repeat(' ', $space > 0 ? $space : 1) . $right;
        };

        $alignCenter = function ($text) use ($WIDTH) {
            if (!$text) return '';
            $lines = explode("\n", $text);
            $result = [];
            foreach ($lines as $l) {
                $space = $WIDTH - strlen($l);
                if ($space <= 0) {
                    $result[] = $l;
                } else {
                    $leftSpace = floor($space / 2);
                    $result[] = str_repeat(' ', $leftSpace) . $l;
                }
            }
            return implode("\n", $result);
        };

        $str = '';

        // HEADER
        $str .= $alignCenter($transaction->cafe->name ?? 'CAFE') . "\n";
        if ($transaction->cafe->address) $str .= $alignCenter($transaction->cafe->address) . "\n";
        if ($transaction->cafe->phone_number) $str .= $alignCenter($transaction->cafe->phone_number) . "\n";
        $str .= $lineStr . "\n";

        // INFO
        $str .= $padRight('No', '#' . $transaction->id) . "\n";
        $str .= $padRight('Tgl', $fmtDate($transaction->updated_at)) . "\n";
        $str .= $padRight('Cust', $transaction->cust_name ?? '-') . "\n";
        if ($transaction->table) $str .= $padRight('Table', $transaction->table->name) . "\n";
        $str .= $padRight('Pay', $transaction->payment_type) . "\n";
        $str .= $lineStr . "\n";

        // ITEMS
        foreach ($transaction->details as $d) {
            $name = substr($d->menu->name ?? '-', 0, $WIDTH);
            $str .= $name . "\n";

            $qtyPrice = $d->amount . 'x' . $cleanNumber($d->price);
            $subtotal = $cleanNumber($d->price * $d->amount);

            $str .= $padRight($qtyPrice, $subtotal) . "\n";

            if ($d->description) {
                $str .= ' ' . $d->description . "\n";
            }
        }

        $str .= $lineStr . "\n";

        // TOTAL
        $str .= $padRight('Subtotal', $cleanNumber($transaction->price)) . "\n";
        $str .= $padRight('Fee', $cleanNumber($transaction->fee)) . "\n";

        $str .= $lineStr . "\n";
        $str .= $padRight('TOTAL', $cleanNumber($transaction->total_price)) . "\n";
        $str .= $lineStr . "\n";

        // FOOTER
        $str .= $alignCenter('Terima kasih') . "\n";
        $str .= "\n\n\n";

        // Replace \n with <br /> for Bluetooth Print app
        $str = str_replace("\n", '<br />', $str);

        // sending image entry
        $imageObj = new \stdClass();
        $imageObj->type = 1; // image
        $imageObj->path = asset('logo-resize.png'); // complete filepath
        $imageObj->align = 1; // center align
        array_push($a, $imageObj);

        // sending multi lines text
        $obj = new \stdClass();
        $obj->type = 0;
        $obj->content = $str;
        $obj->bold = 0;
        $obj->align = 0;

        array_push($a, $obj);

        return response()->json($a, 200, [], JSON_FORCE_OBJECT);
    }
}
