<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use App\Services\TransactionService;

class MakeFailedTransactionIfExpired implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    protected $transactionId;

    public function __construct($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Checking for expired transaction', [
            'transaction_id' => $this->transactionId,
        ]);
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction) return;
        if ($transaction->status !== 'pending') return;

        Log::info('Transaction is still pending, marking as failed', [
            'transaction_id' => $this->transactionId,
        ]);
        TransactionService::makeFailed($transaction);
    }
}
