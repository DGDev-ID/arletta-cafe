<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\Balance;
use App\Models\BalanceLog;
use App\Models\BalanceTopup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class QrislyWebhookController extends ApiBaseController
{
    /**
     * Handle Qrisly payment webhook notification.
     *
     * Qrisly posts a JSON payload on successful payment:
     * {
     *   "secret_key": "<THIRDPARTY_SECRET_KEY>",
     *   "history_id": 230,
     *   "qris_id": 230,
     *   "amount": 50250,
     *   "payment_status": "paid",
     *   ...
     * }
     *
     * - qris_id === QRISLY_TOPUP_QRIS_ID (231)  → balance topup flow
     * - qris_id === QRISLY_ARLETTA_QRIS_ID (230) → merchant payment (logged only for now)
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('Qrisly Webhook Received', $payload);

            // ── 1. Validate secret key ───────────────────────────────────────
            $expectedSecret = config('services.qrisly.secret_key');
            $receivedSecret = $payload['secret_key'] ?? $request->header('X-Secret-Key');
            
            if (!Hash::check($expectedSecret, $receivedSecret)) {
                Log::warning('Qrisly Webhook: invalid secret key', [
                    'received' => $receivedSecret,
                ]);
                return $this->success(null, 'Unauthorized', 200);
            }

            $paymentStatus = $payload['data']['status'] ?? null;
            $historyId     = $payload['data']['qris_history_id']     ?? null;
            $qrisId        = (int) ($payload['data']['qris_id'] ?? 0);
            $amount        = (float) ($payload['data']['original_amount'] ?? 0);
            $topupQrisId = (int) config('services.qrisly.topup_qris_id');

            // ── 2. Topup flow ────────────────────────────────────────────────
            if ($qrisId === $topupQrisId) {
                return $this->handleTopupWebhook((int) $historyId, $amount, $paymentStatus);
            }

            // ── 3. Arletta merchant / other QRIS – log and ack ──────────────
            Log::info('Qrisly Webhook: merchant payment received', [
                'qris_id'    => $qrisId,
                'history_id' => $historyId,
                'amount'     => $amount,
            ]);

            return $this->success(null, 'Merchant payment webhook acknowledged.');
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function handleTopupWebhook(int $historyId, float $paidAmount, string $paymentStatus): JsonResponse
    {
        // Find the pending topup by qris_history_id (idempotency: skip if already processed)
        $topup = BalanceTopup::where('qris_history_id', $historyId)->where('topup_amount', $paidAmount)->first();

        if (! $topup) {
            Log::warning('Qrisly Topup Webhook: no matching BalanceTopup found', [
                'history_id' => $historyId,
            ]);
            // Return 200 so Qrisly stops retrying; we just log the miss.
            return $this->success(null, 'No matching topup record.');
        }

        // Idempotency guard – already processed
        if ($topup->status !== 'pending') {
            Log::info('Qrisly Topup Webhook: topup already processed', [
                'topup_id' => $topup->id,
                'status'   => $topup->status,
            ]);
            return $this->success(null, 'Already processed.');
        }

        $status = null;
        if ($paymentStatus == 'paid' || $paymentStatus == 'completed') {
            $status = 'success';
        }

        if ($paymentStatus == 'expired') {
            $status = 'failed';
        }

        DB::transaction(function () use ($topup, $status) {
            // Mark topup as success
            $topup->update(['status' => $status]);

            if ($status == 'success') {
                // Add balance
                $balance = $topup->balance;
                $balance->increment('balance', $topup->topup_amount);

                // Log the balance credit
                BalanceLog::create([
                    'balance_id'     => $balance->id,
                    'type'           => 'in',
                    'amount'         => $topup->topup_amount,
                    'transaction_id' => $topup->id,
                ]);
            }
        });

        Log::info('Qrisly Topup Webhook: topup processed successfully', [
            'topup_id'     => $topup->id,
            'topup_amount' => $topup->topup_amount,
        ]);

        return $this->success(null, 'Topup berhasil diproses.');
    }
}

