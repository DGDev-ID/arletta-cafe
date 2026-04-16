<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        // Konfigurasi Midtrans dari file .env
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public static function getSnapToken(Transaction $transaction)
    {
        $enabledPayments = ['qris'];
        $order_id = 'cafe-' . $transaction->id . '-' . time() . '-' . rand(1000, 9999);

        $params = [
            'transaction_details' => [
                'order_id' => $order_id,
                'gross_amount' => (int) $transaction->total_price,
            ],
            'customer_details' => [
                'first_name' => $transaction->cust_name,
            ],
            'enabled_payments' => $enabledPayments,
            'item_details' => [
                [
                    'id' => $order_id,
                    'price' => (int) $transaction->total_price,
                    'quantity' => 1,
                    'name' => "Pembayaran " . $transaction->transaction_type,
                ]
            ]
        ];

        $transaction->midtrans_transaction_id = $order_id;
        $transaction->save();

        return Snap::getSnapToken($params);
    }

    public static function handleWebhook($payload)
    {
        $serverKey = config('midtrans.server_key');
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureKey !== $expectedSignature) {
            Log::warning('Midtrans Webhook: Invalid signature', ['order_id' => $orderId]);
            return false;
        }

        $transactionStatus = $payload['transaction_status'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $transaction = Transaction::where('midtrans_transaction_id', $orderId)->first();

        if (!$transaction) {
            Log::warning('Midtrans Webhook: Transaction not found', ['order_id' => $orderId]);
            return false;
        }

        switch ($transactionStatus) {
            case 'settlement':
                $transaction->update(['status' => 'success']);
                break;
            case 'capture':
                $transaction->update(['status' => 'success']);
                break;
            case 'accept':
                $transaction->update(['status' => 'success']);
                break;
            case 'pending':
                $transaction->update(['status' => 'pending']);
                break;
            case 'deny':
                $transaction->update(['status' => 'failed']);
                break;
            case 'expire':
                $transaction->update(['status' => 'failed']);
                break;
            case 'cancel':
                $transaction->update(['status' => 'failed']);
                break;
            default:
                $transaction->update(['status' => 'unknown']);
                break;
        }

        return true;
    }
}
