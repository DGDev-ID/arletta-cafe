<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Xendit\Configuration;
use Xendit\PaymentRequest\PaymentRequestApi;
use Xendit\PaymentRequest\PaymentRequestParameters;

class XenditService
{
    public function __construct()
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));
    }

    public static function createQr(Transaction $transaction)
    {
        Configuration::setXenditKey(config('services.xendit.secret_key'));

        $apiInstance = new PaymentRequestApi();

        try {

            $params = new PaymentRequestParameters([
                'reference_id' => $transaction->unique_code,
                'amount' => (float) $transaction->total_price,
                'currency' => 'IDR',
                'description' => 'Pembayaran ' . $transaction->transaction_type,

                'payment_method' => [
                    'type' => 'QR_CODE',
                    'reusability' => 'ONE_TIME_USE',

                    'qr_code' => [
                        'channel_code' => 'QRIS',
                    ],
                ],
            ]);

            $result = $apiInstance->createPaymentRequest(
                $transaction->unique_code,
                null,
                null,
                $params
            );
            Log::info('Xendit QR Creation Result', [
                'transaction_id' => $transaction->id,
                'xendit_response' => $result
            ]);

            // ambil QR string dari response
            $qrString = $result
                ->getPaymentMethod()
                ->getQrCode()
                ->getChannelProperties()
                ->getQrString();

            $transaction->midtrans_transaction_id = $result->getId();
            $transaction->snap_token = $qrString;
            $transaction->status = 'pending';
            $transaction->save();

            return [
                'id' => $result->getId(),
                'reference_id' => $transaction->unique_code,
                'qr_string' => $qrString,
                'amount' => $transaction->total_price,
                'status' => $result->getStatus(),
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
            return false;
        }

        // 2. Ambil data dari payload (Xendit v7 biasanya nested di "data")
        $data = $payload['data'] ?? $payload;

        $xenditTransactionId = $data['id'] ?? null;
        $referenceId = $data['reference_id'] ?? null;
        $status = $data['status'] ?? null;
        $paymentId = $data['id'] ?? null;

        if (!$referenceId) {
            Log::warning('Xendit Webhook: Missing reference_id', $payload);
            return false;
        }

        $transaction = Transaction::where('midtrans_transaction_id', $xenditTransactionId)->first();

        if (!$transaction) {
            Log::warning('Xendit Webhook: Transaction not found', [
                'xendit_transaction_id' => $xenditTransactionId,
            ]);
            return false;
        }

        // 3. Update status berdasarkan Xendit
        switch ($status) {

            case 'SUCCEEDED':
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
