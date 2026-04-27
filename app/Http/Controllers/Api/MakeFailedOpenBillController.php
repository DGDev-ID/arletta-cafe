<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafeTable;
use App\Models\Transaction;
use Illuminate\Http\Request;

class MakeFailedOpenBillController extends ApiBaseController
{
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'cafe_table_id' => ['required', 'integer', 'exists:m_cafe_tables,id'],
            ]);

            $table = MCafeTable::find($validated['cafe_table_id']);

            if (!$table) {
                return $this->clientError('Table not found');
            }

            if ((int)$table->is_open_bill !== 1) {
                return $this->clientError('Meja ini tidak mendukung open bill');
            }

            $transaction = Transaction::where('table_id', $table->id)
                ->where('status', 'pending')
                ->where('is_open_bill', 1)
                ->orderBy('id', 'desc')
                ->first();

            if (!$transaction) {
                return $this->clientError('Tidak ada open bill pending di meja ini');
            }

            if ($transaction->details()->exists()) {
                return $this->clientError('Tidak bisa menandai open bill gagal karena sudah memiliki pesanan');
            }

            $transaction->status = 'failed';
            $transaction->save();

            return $this->success($transaction, 'Open bill berhasil ditandai gagal');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->clientError('Validation failed', $ve->errors());
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
