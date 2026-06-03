<?php

namespace App\Http\Controllers\Dashboard\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MaterialInboundOutbound;
use App\Models\MaterialVariant;
use App\Models\MCafe;
use App\Models\MMaterial;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::where('status', 'success')
            ->with(['cafe', 'table']);

        if ($request->filled('cafe_id')) {
            $query->where('cafe_id', $request->cafe_id);
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }

        $data = $query->latest('updated_at')->paginate(10)->withQueryString();
        $cafes = MCafe::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('transaction/history/Index', [
            'data' => $data,
            'cafes' => $cafes,
            'filters' => [
                'cafe_id' => $request->input('cafe_id', ''),
                'payment_type' => $request->input('payment_type', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }

    public function show($id)
    {
        $transaction = Transaction::where('status', 'success')
            ->with([
                'cafe',
                'table',
                'details.menu.category',
                'details.menu.promo',
            ])
            ->findOrFail($id);

        // Enrich selected_variants for each detail with names from DB
        $transaction->details->each(function ($detail) {
            $detail->selected_variants = $this->enrichSelectedVariants($detail->selected_variants ?? []);
        });

        return Inertia::render('transaction/history/Show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Enrich selected_variants array with material_name and variant_name
     * from the database, resolving entries that only have IDs.
     */
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

    public function export(Request $request)
    {
        $withDetails = $request->input('with_details', false);

        $query = Transaction::where('status', 'success')
            ->with(['cafe', 'table']);

        if ($withDetails) {
            $query->with(['details.menu']);
        }

        if ($request->filled('cafe_id')) {
            $query->where('cafe_id', $request->cafe_id);
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }

        $transactions = $query->latest('updated_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transactions');

        if ($withDetails) {
            $sheet->fromArray([
                'No', 'Transaction ID', 'Cafe', 'Table', 'Price', 'Fee',
                'Total Price', 'Payment Type', 'Status', 'Transaction Success At', 'Profit Margin', 'Description',
                'Detail - Menu', 'Detail - Amount', 'Detail - Price', 'Detail - Description',
            ], null, 'A1');

            $row = 2;
            $no = 1;
            foreach ($transactions as $trx) {
                $details = $trx->details;
                if ($details->isEmpty()) {
                    $sheet->fromArray([
                        $no++,
                        $trx->unique_code,
                        $trx->cafe->name ?? '-',
                        $trx->table->name ?? '-',
                        $trx->price,
                        $trx->fee,
                        $trx->total_price,
                        $trx->payment_type,
                        $trx->status,
                        $trx->updated_at->format('Y-m-d H:i:s'),
                        $trx->profit_margin,
                        $trx->description,
                        '-', '-', '-', '-',
                    ], null, "A{$row}");
                    $row++;
                } else {
                    foreach ($details as $i => $detail) {
                        $sheet->fromArray([
                            $i === 0 ? $no : '',
                            $i === 0 ? $trx->unique_code : '',
                            $i === 0 ? ($trx->cafe->name ?? '-') : '',
                            $i === 0 ? ($trx->table->name ?? '-') : '',
                            $i === 0 ? $trx->price : '',
                            $i === 0 ? $trx->fee : '',
                            $i === 0 ? $trx->total_price : '',
                            $i === 0 ? $trx->payment_type : '',
                            $i === 0 ? $trx->status : '',
                            $i === 0 ? $trx->updated_at->format('Y-m-d H:i:s') : '',
                            $i === 0 ? $trx->profit_margin : '',
                            $detail->menu->name ?? '-',
                            $detail->amount,
                            $detail->price,
                            $detail->description ?? '-',
                        ], null, "A{$row}");
                        $row++;
                    }
                    $no++;
                }
            }
        } else {
            $sheet->fromArray([
                'No', 'Transaction ID', 'Cafe', 'Table', 'Price', 'Fee',
                'Total Price', 'Payment Type', 'Status', 'Transaction Success At', 'Description', 'Profit Margin'
            ], null, 'A1');

            $row = 2;
            foreach ($transactions as $i => $trx) {
                $sheet->fromArray([
                    $i + 1,
                    $trx->unique_code,
                    $trx->cafe->name ?? '-',
                    $trx->table->name ?? '-',
                    $trx->price,
                    $trx->fee,
                    $trx->total_price,
                    $trx->payment_type,
                    $trx->status,
                    $trx->updated_at->format('Y-m-d H:i:s'),
                    $trx->description,
                    $trx->profit_margin
                ], null, "A{$row}");
                $row++;
            }
        }

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Bold header row
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')
            ->getFont()->setBold(true);

        $filename = 'transactions_' . now()->format('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function makeFailed($id)
    {
        $transaction = Transaction::where('status', 'success')->findOrFail($id);
        TransactionService::makeFailed($transaction);

        return redirect()
            ->route('transaction.history.index')
            ->with('success', 'Transaksi ditolak.');
    }

    public function voidDetail($id)
    {
        $detail = TransactionDetail::with(['transaction', 'menu'])->findOrFail($id);
        $transaction = $detail->transaction;

        if (!$transaction || $transaction->status !== 'success') {
            return redirect()->back()->with('error', 'Transaksi tidak valid untuk aksi ini.');
        }

        // Cannot reduce if this is the last item AND qty is already 1
        if ($detail->amount <= 1 && $transaction->details()->count() <= 1) {
            return redirect()->back()->with('error', 'Tidak bisa void: transaksi harus memiliki minimal 1 item. Gunakan "Tolak Transaksi" untuk membatalkan semua.');
        }

        DB::transaction(function () use ($detail, $transaction) {
            $originalAmount = $detail->amount;

            // Reverse 1-unit worth of material outbounds for this detail
            // per-unit reversal = remaining_unreversed / current_amount
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

                // Reverse only 1 unit worth
                $reverseAmount = $remainingOutbound / $originalAmount;

                MaterialInboundOutbound::create([
                    'material_id'           => $outbound->material_id,
                    'variant_id'            => $outbound->variant_id ?? null,
                    'type'                  => 'inbound',
                    'amount'                => $reverseAmount,
                    'base_unit_id'          => $outbound->base_unit_id,
                    'transaction_detail_id' => $outbound->transaction_detail_id,
                    'inbound_buy_price'     => null,
                ]);
            }

            // Reduce qty by 1 or delete if qty was 1
            if ($originalAmount <= 1) {
                $detail->delete();
            } else {
                $unitPrice      = $detail->menu ? (float) $detail->menu->price : ((float) $detail->price / $originalAmount);
                $detail->amount = $originalAmount - 1;
                $detail->price  = $unitPrice * $detail->amount;
                $detail->save();
            }

            // Recalculate transaction totals
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

            // Recalculate profit margin
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

        return redirect()->back()->with('success', 'Qty item berhasil dikurangi 1.');
    }
}
