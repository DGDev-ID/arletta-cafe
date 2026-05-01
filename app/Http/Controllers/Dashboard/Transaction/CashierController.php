<?php

namespace App\Http\Controllers\Dashboard\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Models\TransactionDetail;
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
            ->where('is_open_bill', 0)
            ->with(['cafe', 'table']);

        // Open-bill pending transactions
        $openBillQuery = Transaction::where('status', 'pending')
            ->where('is_open_bill', 1)
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
            $openBillQuery->whereIn('cafe_id', $allowedCafeIds);
        }

        if ($cafeId) {
            // Ensure requested cafeId is allowed if restrictions apply
            if ($allowedCafeIds === null || $allowedCafeIds->contains($cafeId)) {
                $pendingQuery->where('cafe_id', $cafeId);
                $inOrderQuery->where('cafe_id', $cafeId);
                $successQuery->where('cafe_id', $cafeId);
                $openBillQuery->where('cafe_id', $cafeId);
            }
        }

        // Transaction details that belong to open-bill & pending transactions
        $openBillDetailsQuery = TransactionDetail::whereHas('transaction', function ($q) use ($allowedCafeIds, $cafeId) {
            $q->where('status', 'pending')->where('is_open_bill', 1);
            if ($allowedCafeIds !== null) {
                $q->whereIn('cafe_id', $allowedCafeIds);
            }
            if ($cafeId) {
                $q->where('cafe_id', $cafeId);
            }
        })->with(['transaction.cafe', 'transaction.table', 'menu']);

        return Inertia::render('transaction/cashier/Index', [
            'pendingTransactions' => $pendingQuery->latest()->get(),
            'openBillPendingTransactions' => $openBillQuery->latest()->get(),
            'inOrderTransactions' => $inOrderQuery->latest()->get(),
            'successTransactions' => $successQuery->latest()->get(),
            'openBillPendingDetails' => $openBillDetailsQuery->latest()->get(),
            'cafes' => $cafes,
            'filters' => [
                'cafe_id' => $cafeId ?? '',
            ],
        ]);
    }

    public function makeDetailSuccess($id)
    {
        $detail = TransactionDetail::with('transaction')->findOrFail($id);

        $transaction = $detail->transaction;

        if (!$transaction || $transaction->status !== 'pending' || (int)$transaction->is_open_bill !== 1) {
            return redirect()->route('transaction.cashier.index')->with('error', 'Transaksi tidak valid untuk aksi ini.');
        }

        if ($detail->status === 'success') {
            return redirect()->route('transaction.cashier.index')->with('info', 'Detail sudah diselesaikan.');
        }

        // set detail success
        $detail->status = 'success';
        $detail->save();

        // recalc transaction totals based on success details
        $newPrice = $transaction->details()->where('status', 'success')->sum('price');
        $cafe = MCafe::find($transaction->cafe_id);
        $ppn = $cafe->ppn_fee > 0 ? ($newPrice * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && $transaction->payment_type === 'qris' ? ($newPrice * ($cafe->qris_fee / 100)) : 0;
        $newFee = $ppn + $paymentTypeFee;
        $newTotal = $newPrice + $newFee;

        $transaction->update([
            'price' => $newPrice,
            'fee' => $newFee,
            'total_price' => $newTotal,
        ]);

        return redirect()->route('transaction.cashier.index')->with('success', 'Detail transaksi ditandai selesai.');
    }

    public function detailReceiptData($id)
    {
        $detail = TransactionDetail::with(['transaction.cafe', 'transaction.table', 'menu'])->findOrFail($id);

        return response()->json($detail);
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

    public function applyPromo(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'promo_code' => 'required|string',
        ]);

        $transaction = Transaction::where('id', $request->transaction_id)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($transaction->promo_id !== null) {
            return redirect()->back()->with('error', 'Transaksi ini sudah menggunakan promo.');
        }

        $promo = \App\Models\CafePromo::where('cafe_id', $transaction->cafe_id)
            ->where('promo_code', $request->promo_code)
            ->where('status', true)
            ->first();

        if (!$promo) {
            return redirect()->back()->with('error', 'Kode promo tidak valid atau tidak aktif.');
        }

        $price = $transaction->price;
        $discountAmount = 0;

        if ($promo->type === 'discount_percent') {
            $discountAmount = $price * ($promo->value / 100);
        } else if ($promo->type === 'discount_amount') {
            $discountAmount = $promo->value;
        }

        if ($discountAmount > $price) {
            $discountAmount = $price;
        }

        $priceAfterDiscount = $price - $discountAmount;

        $cafe = MCafe::find($transaction->cafe_id);
        $ppn = $cafe->ppn_fee > 0 ? ($priceAfterDiscount * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && $transaction->payment_type === 'qris' ? ($priceAfterDiscount * ($cafe->qris_fee / 100)) : 0;
        
        $newFee = $ppn + $paymentTypeFee;
        $newTotal = $priceAfterDiscount + $newFee;

        $transaction->update([
            'promo_id' => $promo->id,
            'fee' => $newFee,
            'total_price' => $newTotal,
        ]);

        return redirect()->back()->with('success', 'Promo berhasil digunakan.');
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
            return number_format($val, 0, ',', '.');
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

            $qtyPrice = $d->amount . 'x' . $cleanNumber($d->menu->price ?? 0);
            $subtotal = $cleanNumber($d->price);

            $str .= $padRight($qtyPrice, $subtotal) . "\n";

            if ($d->description) {
                $str .= $d->description . "\n";
            }
        }

        $str .= $lineStr . "\n";

        // TOTAL
        $str .= $padRight('Subtotal', $cleanNumber($transaction->price)) . "\n";
        $str .= $padRight('Fee', $cleanNumber($transaction->fee)) . "\n";

        $discount = $transaction->total_price - $transaction->price;
        if($discount > 0) {
            $str .= $padRight('Discount', $cleanNumber($discount)) . "\n";
        }

        $str .= $lineStr . "\n";
        $str .= $padRight('TOTAL', $cleanNumber($transaction->total_price)) . "\n";
        $str .= $lineStr . "\n";

        // FOOTER
        $str .= $alignCenter('Terima kasih') . "\n";
        $str .= $lineStr . "\n";
        $str .= $lineStr . "\n";
        $str .= "\n";

        // Replace \n with <br /> for Bluetooth Print app
        $str = str_replace("\n", '<br />', $str);

        // sending image entry
        $imageObj = new \stdClass();
        $imageObj->type = 1; // image
        $imageObj->path = "https://dashboard-cafe.arlettaluxury.com/logo-resize.png"; // complete filepath
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
