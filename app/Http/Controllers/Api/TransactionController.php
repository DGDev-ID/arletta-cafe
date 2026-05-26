<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Services\MidtransService;
use App\Services\XenditService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(TransactionRequest $request)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validated();
            $transaction = TransactionService::makeTransaction($validated);
            TransactionService::pendingAction($transaction);

            $qrResult = null;
            if ($transaction->payment_type === 'qris') {
                $qrResult = XenditService::createQr($transaction);
                if (!$qrResult) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal membuat QRIS Xendit'
                    ], 500);
                }
                $transaction->snap_token = $qrResult['qr_string']; // atau rename field aja biar gak bingung
                $transaction->save();
            }

            $dataSend = [
                'transaction_id' => $transaction->id,
                'cafe_name' => $transaction->cafe->name,
                'table_name' => $transaction->table->name,
                'price' => $transaction->price,
                'fee' => $transaction->fee,
                'total_price' => $transaction->total_price,
                'payment_type' => $transaction->payment_type,
                'cust_name' => $transaction->cust_name,
                'details' => $transaction->details->map(function ($detail) {
                    return [
                        'menu_name' => $detail->menu->name,
                        'amount' => $detail->amount,
                        'price' => $detail->price,
                        'description' => $detail->description,
                    ];
                }),
            ];
            if ($transaction->payment_type === 'qris') {
                $dataSend['snap_token'] = $transaction->snap_token;
                $dataSend['expired_at'] = $qrResult['expires_at'];
            }

            if ($transaction->payment_type === 'manual') {
                $dataSend['qr_code'] = $transaction->unique_code;
            }

            DB::commit();
            return $this->success($dataSend, 'Transaction created successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->serverError($th);
        }
    }
}
