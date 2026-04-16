<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Services\MidtransService;
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
            
            if($transaction->payment_type === 'qris') {
                $snapToken = MidtransService::getSnapToken($transaction);
                $transaction->snap_token = $snapToken;
                $transaction->save();
            }

            return $this->success($transaction);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->serverError($th);
        }
    }
}
