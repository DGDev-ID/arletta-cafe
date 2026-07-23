<?php

namespace App\Http\Controllers\Dashboard\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialInboundOutbound;
use App\Models\MaterialVariant;
use App\Models\MCafe;
use App\Models\MMenu;
use App\Models\MMaterial;
use App\Models\ThirdPartyChannel;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $cafes = MCafe::select('id', 'name')->orderBy('name')->get();
        }

        // Pending manual and debit transactions (non open-bill)
        $pendingQuery = Transaction::where('status', 'pending')
            ->whereIn('payment_type', ['manual', 'debit'])
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
            if ($allowedCafeIds === null || $allowedCafeIds->contains($cafeId)) {
                $pendingQuery->where('cafe_id', $cafeId);
                $inOrderQuery->where('cafe_id', $cafeId);
                $successQuery->where('cafe_id', $cafeId);
                $openBillQuery->where('cafe_id', $cafeId);
            }
        }

        $openBillDetailsQuery = TransactionDetail::whereHas('transaction', function ($q) use ($allowedCafeIds, $cafeId) {
            $q->where('status', 'pending')->where('is_open_bill', 1);
            if ($allowedCafeIds !== null) {
                $q->whereIn('cafe_id', $allowedCafeIds);
            }
            if ($cafeId) {
                $q->where('cafe_id', $cafeId);
            }
        })->with(['transaction.cafe', 'transaction.table', 'menu']);

        $openBillPendingDetails = $openBillDetailsQuery->latest()->get();
        $openBillPendingDetails->each(function ($detail) {
            $detail->selected_variants = $this->enrichSelectedVariants($detail->selected_variants ?? []);
        });

        return Inertia::render('transaction/cashier/Index', [
            'pendingTransactions'         => $pendingQuery->latest()->get(),
            'openBillPendingTransactions' => $openBillQuery->latest()->get(),
            'inOrderTransactions'         => $inOrderQuery->latest()->get(),
            'successTransactions'         => $successQuery->latest()->get(),
            'openBillPendingDetails'      => $openBillPendingDetails,
            'cafes'                       => $cafes,
            'filters'                     => [
                'cafe_id' => $cafeId ?? '',
            ],
            'thirdPartyChannels' => ThirdPartyChannel::where('is_active', true)->orderBy('name')->get(['id', 'name', 'admin_fee']),
        ]);
    }

    public function reduceDetailAmount($id)
    {
        $detail = TransactionDetail::with(['transaction', 'menu'])->findOrFail($id);
        $transaction = $detail->transaction;

        if (!$transaction || $transaction->status !== 'pending' || !in_array($transaction->payment_type, ['manual', 'debit'])) {
            return redirect()->back()->with('error', 'Transaksi tidak valid untuk aksi ini.');
        }

        if ($detail->amount <= 1 && $transaction->details()->count() <= 1) {
            return redirect()->back()->with('error', 'Tidak bisa mengurangi: transaksi harus memiliki minimal 1 item.');
        }

        DB::transaction(function () use ($detail, $transaction) {
            $originalAmount = $detail->amount;

            $outbounds = MaterialInboundOutbound::where('type', 'outbound')
                ->where('transaction_detail_id', $detail->id)
                ->lockForUpdate()
                ->get();

            foreach ($outbounds as $outbound) {
                $alreadyReversed = (float) MaterialInboundOutbound::where('type', 'inbound')
                    ->where('transaction_detail_id', $outbound->transaction_detail_id)
                    ->where('material_id', $outbound->material_id)
                    ->sum('amount');

                $remainingOutbound = (float) $outbound->amount - $alreadyReversed;
                if ($remainingOutbound <= 0) continue;

                $reverseAmount = $remainingOutbound / $originalAmount;

                MaterialInboundOutbound::create([
                    'material_id'           => $outbound->material_id,
                    'type'                  => 'inbound',
                    'amount'                => $reverseAmount,
                    'base_unit_id'          => $outbound->base_unit_id,
                    'transaction_detail_id' => $outbound->transaction_detail_id,
                    'inbound_buy_price'     => null,
                ]);
            }

            if ($originalAmount <= 1) {
                $detail->delete();
            } else {
                $unitPrice      = $detail->menu ? (float) $detail->menu->price : ((float) $detail->price / $originalAmount);
                $detail->amount = $originalAmount - 1;
                $detail->price  = $unitPrice * $detail->amount;
                $detail->save();
            }

            $transaction->refresh();
            $newPrice = $transaction->details()->sum('price');
            $cafe     = MCafe::find($transaction->cafe_id);

            $discountAmount = 0;
            if ($transaction->promo_id) {
                $promo = \App\Models\CafePromo::find($transaction->promo_id);
                if ($promo) {
                    if ($promo->type === 'discount_percent') {
                        $discountAmount = $newPrice * ($promo->value / 100);
                    } elseif ($promo->type === 'discount_amount') {
                        $discountAmount = min((float) $promo->value, $newPrice);
                    }
                }
            }

            $priceAfterDiscount = $newPrice - $discountAmount;
            $ppn                = $cafe->ppn_fee > 0 ? ($priceAfterDiscount * ($cafe->ppn_fee / 100)) : 0;
            $paymentTypeFee     = $cafe->qris_fee > 0 && $transaction->payment_type === 'qris'
                ? ($priceAfterDiscount * ($cafe->qris_fee / 100))
                : 0;
            $newFee   = $ppn + $paymentTypeFee;
            $newTotal = $priceAfterDiscount + $newFee;

            $detailIds       = $transaction->details()->pluck('id')->all();
            $netMaterialCost = 0;

            if (!empty($detailIds)) {
                $allOutbounds = MaterialInboundOutbound::where('type', 'outbound')
                    ->whereIn('transaction_detail_id', $detailIds)
                    ->with('material')
                    ->get();

                $allReversals = MaterialInboundOutbound::where('type', 'inbound')
                    ->whereIn('transaction_detail_id', $detailIds)
                    ->with('material')
                    ->get();

                foreach ($allOutbounds as $o) {
                    $netMaterialCost += (float) $o->amount * (float) $o->material->avg_buy_price;
                }
                foreach ($allReversals as $i) {
                    $netMaterialCost -= (float) $i->amount * (float) $i->material->avg_buy_price;
                }
            }

            $transaction->update([
                'price'         => $newPrice,
                'fee'           => $newFee,
                'total_price'   => $newTotal,
                'profit_margin' => $newTotal - $netMaterialCost,
            ]);
        });

        return redirect()->back()->with('success', 'Jumlah item berhasil dikurangi.');
    }

    public function makeDetailSuccess($id)
    {
        $detail = TransactionDetail::with('transaction')->findOrFail($id);

        $transaction = $detail->transaction;

        if (!$transaction || $transaction->status !== 'pending' || (int) $transaction->is_open_bill !== 1) {
            return redirect()->route('transaction.cashier.index')->with('error', 'Transaksi tidak valid untuk aksi ini.');
        }

        if ($detail->status === 'success') {
            return redirect()->route('transaction.cashier.index')->with('info', 'Detail sudah diselesaikan.');
        }

        $detail->status = 'success';
        $detail->save();

        $newPrice = $transaction->details()->where('status', 'success')->sum('price');
        $cafe = MCafe::find($transaction->cafe_id);
        $ppn = $cafe->ppn_fee > 0 ? ($newPrice * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && $transaction->payment_type === 'qris'
            ? ($newPrice * ($cafe->qris_fee / 100))
            : 0;
        $newFee   = $ppn + $paymentTypeFee;
        $newTotal = $newPrice + $newFee;

        $transaction->update([
            'price'       => $newPrice,
            'fee'         => $newFee,
            'total_price' => $newTotal,
        ]);

        return redirect()->route('transaction.cashier.index')->with('success', 'Detail transaksi ditandai selesai.');
    }

    public function detailReceiptData($id)
    {
        $detail = TransactionDetail::with(['transaction.cafe', 'transaction.table', 'menu'])->findOrFail($id);

        $detail->selected_variants = $this->enrichSelectedVariants($detail->selected_variants ?? []);

        return response()->json($detail);
    }

    private function enrichSelectedVariants(array $variants): array
    {
        if (empty($variants)) return $variants;

        $variantIds  = collect($variants)->pluck('variant_id')->filter()->unique()->values()->all();
        $materialIds = collect($variants)->pluck('material_id')->filter()->unique()->values()->all();

        $variantMap  = MaterialVariant::whereIn('id', $variantIds)->get()->keyBy('id');
        $materialMap = MMaterial::whereIn('id', $materialIds)->get(['id', 'name'])->keyBy('id');

        return collect($variants)->map(function ($sv) use ($variantMap, $materialMap) {
            $variantId  = $sv['variant_id']  ?? null;
            $materialId = $sv['material_id'] ?? null;

            if (empty($sv['variant_name']) && $variantId && isset($variantMap[$variantId])) {
                $sv['variant_name'] = $variantMap[$variantId]->name;
            }
            if (empty($sv['material_name']) && $materialId && isset($materialMap[$materialId])) {
                $sv['material_name'] = $materialMap[$materialId]->name;
            }

            return $sv;
        })->values()->all();
    }

    public function searchByQRCode($qr_code)
    {
        $transaction = Transaction::where('unique_code', $qr_code)
            ->where('status', 'pending')
            ->whereIn('payment_type', ['manual', 'debit'])
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
                $q2->where('status', 'pending')
                   ->whereIn('payment_type', ['manual', 'debit']);
            })->orWhereIn('status', ['in_order', 'success']);
        })
            ->with(['cafe', 'table', 'details.menu.category', 'details.menu.menuCombos.childMenu', 'details.menu.menuComboGroups.options.childMenu'])
            ->findOrFail($id);

        $transaction->details->each(function ($detail) {
            $detail->selected_variants = $this->enrichSelectedVariants($detail->selected_variants ?? []);
        });

        return Inertia::render('transaction/cashier/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Approve pending transaction → in_order.
     *
     * Alur:
     *  - Manual : kasir approve → pendingAction (potong stok) → status in_order
     *  - QRIS   : kasir konfirmasi pembayaran sudah diterima → status in_order
     *             (stok dipotong sama seperti manual via pendingAction)
     */
    public function makeSuccess($id)
    {
        $transaction = Transaction::where('status', 'pending')
            ->whereIn('payment_type', ['manual', 'debit'])
            ->findOrFail($id);

        // pendingAction memotong stok & TransactionService::makeSuccess set ke in_order
        // TransactionService::pendingAction($transaction);
        TransactionService::makeSuccess($transaction);

        return redirect()
            ->route('transaction.cashier.index')
            ->with('success', 'Transaksi berhasil disetujui dan masuk ke antrian.');
    }

    public function makeFailed($id)
    {
        $transaction = Transaction::where('status', 'pending')
            ->whereIn('payment_type', ['manual', 'debit'])
            ->findOrFail($id);

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
            'promo_code'     => 'required|string',
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

        $price          = $transaction->price;
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

        $cafe           = MCafe::find($transaction->cafe_id);
        $ppn            = $cafe->ppn_fee > 0 ? ($priceAfterDiscount * ($cafe->ppn_fee / 100)) : 0;
        $paymentTypeFee = $cafe->qris_fee > 0 && $transaction->payment_type === 'qris'
            ? ($priceAfterDiscount * ($cafe->qris_fee / 100))
            : 0;

        $newFee   = $ppn + $paymentTypeFee;
        $newTotal = $priceAfterDiscount + $newFee;

        $transaction->update([
            'promo_id'    => $promo->id,
            'fee'         => $newFee,
            'total_price' => $newTotal,
        ]);

        return redirect()->back()->with('success', 'Promo berhasil digunakan.');
    }

    public function receiptData($id)
    {
        $transaction = Transaction::whereIn('status', ['in_order', 'success'])
            ->with(['cafe:id,name,address', 'table:id,name', 'details.menu:id,name,price'])
            ->findOrFail($id);

        $transaction->details->each(function ($detail) {
            $detail->selected_variants = $this->enrichSelectedVariants($detail->selected_variants ?? []);
        });

        return response()->json($transaction);
    }

    /**
     * GET /transaction/cashier/menus-by-cafe?cafe_id=X
     * Mengembalikan daftar menu berdasarkan cafe untuk keperluan kasir pihak ketiga.
     */
    public function getMenusByCafe(Request $request)
    {
        $cafeId = $request->input('cafe_id');
        if (!$cafeId) {
            return response()->json([]);
        }

        $menus = MMenu::where('cafe_id', $cafeId)
            ->where('status', 'available')
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'menu_category_id', 'img_url', 'is_combo']);

        return response()->json($menus);
    }

    /**
     * POST /transaction/cashier/third-party
     * Membuat pesanan pihak ketiga langsung dengan status in_order.
     * Admin fee tidak masuk omset — disimpan terpisah di kolom admin_fee.
     */
    public function storeThirdParty(Request $request)
    {
        $validated = $request->validate([
            'cafe_id'                => 'required|exists:m_cafes,id',
            'third_party_channel_id' => 'required|exists:third_party_channels,id',
            'cust_name'              => 'nullable|string|max:255',
            'third_party_reference'  => 'nullable|string|max:255',
            'details'                => 'required|array|min:1',
            'details.*.menu_id'      => 'required|exists:m_menus,id',
            'details.*.amount'       => 'required|integer|min:1',
            'details.*.description'  => 'nullable|string|max:500',
        ]);

        $channel = ThirdPartyChannel::findOrFail($validated['third_party_channel_id']);
        $cafe    = MCafe::findOrFail($validated['cafe_id']);

        $menuIds = collect($validated['details'])->pluck('menu_id')->all();
        $menus   = MMenu::whereIn('id', $menuIds)->where('cafe_id', $cafe->id)->get();

        if ($menus->count() !== count($menuIds)) {
            return redirect()->back()->with('error', 'Beberapa menu tidak ditemukan atau bukan milik cafe ini.');
        }

        // Hitung subtotal (harga murni menu, INI yang jadi omset)
        $price = 0;
        foreach ($validated['details'] as $detail) {
            $menu   = $menus->firstWhere('id', $detail['menu_id']);
            $price += $menu->price * $detail['amount'];
        }

        // PPN dari cafe
        $ppn       = $cafe->ppn_fee > 0 ? ($price * ($cafe->ppn_fee / 100)) : 0;
        $adminFee  = (float) $channel->admin_fee; // flat per nota, TIDAK masuk omset
        $totalPrice = floor($price + $ppn + $adminFee);

        DB::transaction(function () use ($validated, $cafe, $channel, $menus, $price, $ppn, $adminFee, $totalPrice) {
            $transaction = Transaction::create([
                'cafe_id'                => $cafe->id,
                'table_id'               => null,
                'cust_name'              => $validated['cust_name'] ?? $channel->name,
                'price'                  => $price,
                'fee'                    => $ppn,
                'admin_fee'              => $adminFee,
                'total_price'            => $totalPrice,
                'payment_type'           => 'third_party',
                'status'                 => 'in_order', // Langsung in_order
                'third_party_channel_id' => $channel->id,
                'third_party_reference'  => $validated['third_party_reference'] ?? null,
            ]);

            foreach ($validated['details'] as $item) {
                $menu = $menus->firstWhere('id', $item['menu_id']);
                $transaction->details()->create([
                    'menu_id'     => $menu->id,
                    'amount'      => $item['amount'],
                    'price'       => $menu->price * $item['amount'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            // Potong stok bahan baku (sama seperti approve manual)
            TransactionService::pendingAction($transaction);
        });

        return redirect()->route('transaction.cashier.index')
            ->with('success', 'Pesanan ' . $channel->name . ' berhasil diproses dan masuk antrian.');
    }
}