<?php

namespace App\Services;

use App\Models\BalanceTopup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\BalanceLog;
use APp\Models\Balance;

class QrislyService
{
    protected string $apiUrl;
    protected string $secretKey;
    protected string $topupQrisId;
    protected string $arlettaQrisId;

    public function __construct()
    {
        $this->apiUrl        = rtrim(config('services.qrisly.api_url'), '/');
        $this->secretKey     = config('services.qrisly.secret_key');
        $this->topupQrisId   = config('services.qrisly.topup_qris_id');
        $this->arlettaQrisId = config('services.qrisly.arletta_qris_id');
    }

    /**
     * Generate a QRIS for a topup payment.
     *
     * @param  int|float  $amount  The exact amount (after adding unique suffix) in Rupiah
     * @return array|false         Response data or false on failure
     */
    public function generateTopupQris(int|float $amount): array|false
    {
        try {
            Log::info("API URL $this->apiUrl");
            Log::info("Qrisly key $this->secretKey");
            Log::info("Topup qirs id $this->topupQrisId");

            $response = Http::withHeaders([
                'x-api-key'     => $this->secretKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])->post("{$this->apiUrl}/generate-qris", [
                'qris_id'     => (int) $this->topupQrisId,
                'amount'      => (int) $amount,
                'output_type' => 'string',
            ]);

            Log::info('Qrisly generateTopupQris response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if (! $response->successful()) {
                Log::error('Qrisly generateTopupQris failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Qrisly generateTopupQris exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate a QRIS for a transaction (Arletta merchant QRIS).
     *
     * @param  int|float  $amount      Total amount in Rupiah
     * @param  string     $externalId  Unique reference (e.g. transaction ID)
     * @return array|false             ['qr_string' => ..., 'history_id' => ..., 'expires_at' => ...] or false on failure
     */
    public function generateTransactionQris(int|float $amount, string $externalId): array|false
    {
        try {
            Log::info('Qrisly generateTransactionQris', [
                'arletta_qris_id' => $this->arlettaQrisId,
                'amount'          => $amount,
                'external_id'     => $externalId,
            ]);

            $response = Http::withHeaders([
                'x-api-key'    => $this->secretKey,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/generate-qris", [
                'qris_id'     => (int) $this->arlettaQrisId,
                'amount'      => (int) $amount,
                'output_type' => 'string',
                'external_id' => $externalId,
            ]);

            Log::info('Qrisly generateTransactionQris response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if (! $response->successful()) {
                Log::error('Qrisly generateTransactionQris failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            $json     = $response->json();
            $qrisData = $json['data'] ?? $json;

            return [
                'qr_string'  => $qrisData['qris_string'] ?? $qrisData['qr_string'] ?? null,
                'history_id' => $qrisData['history_id']  ?? null,
                'expires_at' => $qrisData['expires_at']  ?? $qrisData['expired_at'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Qrisly generateTransactionQris exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check payment status for a given history_id.
     *
     * @param  int  $historyId
     * @return array|false
     */
    public function checkPaymentStatus(int $id): array|false
    {
        try {
            $response = BalanceTopup::find($id)->toArray();
            return $response;
        } catch (\Throwable $e) {
            Log::error('Qrisly checkPaymentStatus exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    public function handleTransactionWebhook($payload) {
        $qrisHistoryId = $payload['data']['qris_history_id'] ?? null;
        $totalAmount = $payload['data']['amount'] ?? null;

        $transaction = Transaction::where('qris_history_id', (string) $qrisHistoryId)
            ->where('total_price', $totalAmount)
            ->first();

        if (!$transaction) {
            Log::warning('Transaction Webhook: Data transaction tidak ditemukan', [
                'qris_history_id' => $qrisHistoryId,
                'total_amount' => $totalAmount,
            ]);
            return false;
        }

        if ($transaction->status === 'success') {
            Log::info('Transaction Webhook: Transaction sudah berstatus success sebelumnya (idempotent)', [
                'transaction_id' => $transaction->id,
            ]);
            return true;
        }

        $event = strtolower((string) ($payload['event'] ?? ''));
        $dataStatus = strtolower((string) ($payload['data']['status'] ?? ''));

        $targetStatus = null;
        if ($event === 'payment.success' || $dataStatus === 'paid') {
            $targetStatus = 'success';
        } elseif ($event === 'payment.expired' || $dataStatus === 'expired') {
            $targetStatus = 'failed';
        } elseif (in_array($dataStatus, ['pending', 'unpaid', 'processing'])) {
            $targetStatus = 'pending';
        } else {
            Log::warning('Transaction Webhook: Event atau status tidak dikenal', [
                'event'  => $event,
                'status' => $dataStatus,
            ]);
            return false;
        }

        if ($targetStatus == 'success') {
            $balance = Balance::first();
            $balanceLog = BalanceLog::create([
                'balance_id' => $balance->id,
                'type' => 'out',
                'amount' => 500,
                'transaction_id' => $transaction->id,
            ]);
            $balance = $balance - $balanceLog->amount;
            $balance->save();
        }

        $transaction->status = $targetStatus;
        $transaction->save();
        return true;
    }
}
