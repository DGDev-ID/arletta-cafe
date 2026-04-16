<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class CheckTransactionStatusController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Transaction $transaction)
    {
        return $this->success($transaction->status);
    }
}
