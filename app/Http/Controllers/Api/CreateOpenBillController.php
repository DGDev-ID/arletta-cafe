<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafeTable;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateOpenBillController extends ApiBaseController
{
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'cafe_table_id' => ['required', 'integer', 'exists:m_cafe_tables,id'],
                'cust_name' => ['required', 'string'],
            ]);

            $table = MCafeTable::find($validated['cafe_table_id']);

            if (!$table) {
                return $this->clientError('Table not found');
            }

            // Check if this table supports open bill
            if ((int)$table->is_open_bill !== 1) {
                return $this->clientError('Meja ini tidak mendukung open bill');
            }

            // Check existing pending transaction on this table
            $hasPending = Transaction::where('table_id', $table->id)
                ->where('status', 'pending')
                ->exists();

            if ($hasPending) {
                return $this->clientError('Tidak bisa menambah open bill baru di meja ini dikarenakan open bill lama belum dibayar');
            }

            DB::beginTransaction();

            $transaction = Transaction::create([
                'cafe_id' => $table->cafe_id,
                'table_id' => $table->id,
                'cust_name' => $validated['cust_name'] ?? 'Customer',
                'price' => 0,
                'fee' => 0,
                'total_price' => 0,
                'payment_type' => 'manual',
                'status' => 'pending',
                'is_open_bill' => 1,
                'profit_margin' => 0,
            ]);

            $transaction->load('details.menu');

            DB::commit();

            return $this->success($transaction, 'Open bill created');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->serverError($th);
        }
    }
}
