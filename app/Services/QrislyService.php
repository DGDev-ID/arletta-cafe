<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QrislyService
{
    protected string $apiUrl;
    protected string $secretKey;
    protected string $topupQrisId;

    public function __construct()
    {
        $this->apiUrl      = rtrim(config('services.qrisly.api_url'), '/');
        $this->secretKey   = config('services.qrisly.secret_key');
        $this->topupQrisId = config('services.qrisly.topup_qris_id');
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])->post("{$this->apiUrl}/generate-qris", [
                'qris_id'     => $this->topupQrisId,
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
     * Check payment status for a given history_id.
     *
     * @param  int  $historyId
     * @return array|false
     */
    public function checkPaymentStatus(int $historyId): array|false
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept'        => 'application/json',
            ])->get("{$this->apiUrl}/payment-status/{$historyId}");

            Log::info('Qrisly checkPaymentStatus response', [
                'history_id' => $historyId,
                'status'     => $response->status(),
                'body'       => $response->json(),
            ]);

            if (! $response->successful()) {
                Log::error('Qrisly checkPaymentStatus failed', [
                    'history_id' => $historyId,
                    'body'       => $response->body(),
                ]);
                return false;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('Qrisly checkPaymentStatus exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
