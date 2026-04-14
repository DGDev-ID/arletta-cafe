<?php

namespace App\Http\Controllers\Dashboard\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\Transaction;
use Illuminate\Http\Request;
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

        return Inertia::render('transaction/history/Show', [
            'transaction' => $transaction,
        ]);
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
                'Total Price', 'Payment Type', 'Status', 'Updated At',
                'Detail - Menu', 'Detail - Amount', 'Detail - Price', 'Detail - Description',
            ], null, 'A1');

            $row = 2;
            $no = 1;
            foreach ($transactions as $trx) {
                $details = $trx->details;
                if ($details->isEmpty()) {
                    $sheet->fromArray([
                        $no++,
                        $trx->id,
                        $trx->cafe->name ?? '-',
                        $trx->table->name ?? '-',
                        $trx->price,
                        $trx->fee,
                        $trx->total_price,
                        $trx->payment_type,
                        $trx->status,
                        $trx->updated_at->format('Y-m-d H:i:s'),
                        '-', '-', '-', '-',
                    ], null, "A{$row}");
                    $row++;
                } else {
                    foreach ($details as $i => $detail) {
                        $sheet->fromArray([
                            $i === 0 ? $no : '',
                            $i === 0 ? $trx->id : '',
                            $i === 0 ? ($trx->cafe->name ?? '-') : '',
                            $i === 0 ? ($trx->table->name ?? '-') : '',
                            $i === 0 ? $trx->price : '',
                            $i === 0 ? $trx->fee : '',
                            $i === 0 ? $trx->total_price : '',
                            $i === 0 ? $trx->payment_type : '',
                            $i === 0 ? $trx->status : '',
                            $i === 0 ? $trx->updated_at->format('Y-m-d H:i:s') : '',
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
                'Total Price', 'Payment Type', 'Status', 'Updated At',
            ], null, 'A1');

            $row = 2;
            foreach ($transactions as $i => $trx) {
                $sheet->fromArray([
                    $i + 1,
                    $trx->id,
                    $trx->cafe->name ?? '-',
                    $trx->table->name ?? '-',
                    $trx->price,
                    $trx->fee,
                    $trx->total_price,
                    $trx->payment_type,
                    $trx->status,
                    $trx->updated_at->format('Y-m-d H:i:s'),
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
}
