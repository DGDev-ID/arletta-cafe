<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\Transaction;
use App\Models\MCafeTable;
use App\Models\MCafe;
use App\Services\XenditService;
use Illuminate\Http\Request;

class PayOpenbillQrisController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'cafe_unique_id' => ['required', 'exists:m_cafes,unique_id'],
            'table_id' => ['required', 'exists:m_cafe_tables,id'],
        ]);
        
        if (!$validated) {
            return $this->clientError('Invalid input');
        }

        $cafe = MCafe::where('unique_id', $validated['cafe_unique_id'])->first();
        if (!$cafe) {
            return $this->clientError('Cafe not found');
        }
        $table = MCafeTable::where('id', $validated['table_id'])->where('cafe_id', $cafe->id)->first();
        if (!$table) {
            return $this->clientError('Table not found');
        }

        $checkPendingOpenBillTrx = Transaction::where('cafe_id', $cafe->id)
            ->where('table_id', $table->id)
            ->where('is_open_bill', 1)
            ->where('status', 'pending')
            ->first();

        if (!$checkPendingOpenBillTrx) {
            return $this->clientError('Tidak ditemukan transaksi open bill pending untuk meja ini');
        }

        $responseXendit = XenditService::createQr($checkPendingOpenBillTrx);

        return $this->success([
            'transaction' => $checkPendingOpenBillTrx,
            'xendit_response' => $responseXendit,
        ]);
    }
}
