<?php

namespace App\Services;

use App\Jobs\MakeFailedTransactionIfExpired;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;

class XenditService
{
    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
    }

    public static function createQr(Transaction $transaction)
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
        
        try {
            $minutesToExpire = 1;
            $expiredAt = now()->addMinutes($minutesToExpire);

            $result = Http::withBasicAuth(
                config('services.xendit.secret_key'),
                ''
            )->post('https://api.xendit.co/qr_codes', [
                'external_id' => $transaction->unique_code,
                'type' => 'DYNAMIC',
                'currency' => 'IDR',
                'amount' => (float) $transaction->total_price,

                'expires_at' => $expiredAt->toIso8601String(),

                'callback_url' => config('services.xendit.webhook_url'),
            ]);
            MakeFailedTransactionIfExpired::dispatch($transaction->id)->delay($expiredAt);

            Log::info('Xendit QR Creation Result', [
                'transaction_id' => $transaction->id,
                'xendit_response' => $result->json()
            ]);

            $data = $result->json();

            // ambil QR string dari response
            $qrString = $data['qr_string'] ?? null;

            $transaction->midtrans_transaction_id = $data['id'];
            $transaction->snap_token = $qrString;
            $transaction->status = 'pending';
            $transaction->save();

            return [
                'id' => $data['id'],
                'reference_id' => $transaction->unique_code,
                'qr_string' => $qrString,
                'amount' => $transaction->total_price,
                'status' => $data['status'],
                'expires_at' => $expiredAt
            ];
        } catch (\Exception $e) {

            Log::error('Xendit PaymentRequest QR Error', [
                'message' => $e->getMessage()
            ]);

            return false;
        }
    }

    public static function handleWebhook($payload, $callbackToken)
    {
        $expectedToken = config('services.xendit.webhook_secret');

        // 1. Validasi callback token
        if ($callbackToken !== $expectedToken) {
            Log::warning('Xendit Webhook: Invalid callback token');
            return true;
        }

        if ($payload['event'] != "qr.payment") {
            Log::info('Xendit Webhook: Ignored event type', ['event' => $payload['event']]);
            return true;
        }

        // // 2. Ambil data dari payload (Xendit v7 biasanya nested di "data")
        // $data = $payload['data'] ?? $payload;

        // $xendit_transaction_id = $data['payment_request_id'] ?? null;
        // $referenceId = $data['reference_id'] ?? null;
        // $status = $data['status'] ?? null;
        // $paymentId = $data['id'] ?? null;
        $referenceId = $payload['qr_code']['external_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$referenceId) {
            Log::warning('Xendit Webhook: Missing reference_id', $payload);
            return true;
        }

        $transaction = Transaction::where('unique_code', $referenceId)->first();

        if (!$transaction) {
            Log::warning('Xendit Webhook: Transaction not found', [
                'reference_id' => $referenceId,
            ]);
            return true;
        }

        // 3. Update status berdasarkan Xendit
        switch ($status) {

            case 'SUCCEEDED':
            case 'COMPLETED':
            case 'PAID':
                $transaction->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
                break;

            case 'PENDING':
            case 'ACTIVE':
                $transaction->update([
                    'status' => 'pending',
                ]);
                break;

            case 'EXPIRED':
                $transaction->update([
                    'status' => 'expired',
                ]);
                break;

            case 'FAILED':
            case 'CANCELLED':
                $transaction->update([
                    'status' => 'failed',
                ]);
                break;

            default:
                $transaction->update([
                    'status' => 'unknown',
                ]);
                break;
        }

        // 4. log success biar gampang debug
        Log::info('Xendit Webhook Processed', [
            'reference_id' => $referenceId,
            'status' => $status,
            'payment_id' => $paymentId
        ]);

        return true;
    }
}
