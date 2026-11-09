<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Balance;
use App\Models\BalanceLog;
use App\Models\BalanceTopup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramService;

class TopupBalanceWebhookController extends Controller
{
    /**
     * Handle incoming Top Up Balance Webhook from core.cafellio.my.id
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('Topup Balance Webhook received', [
                'ip'      => $request->ip(),
                'headers' => [
                    'x-thirdparty-key' => $request->hasHeader('x-thirdparty-key') ? '***present***' : 'missing',
                    'origin'           => $request->headers->get('origin'),
                    'referer'          => $request->headers->get('referer'),
                ],
                'payload' => $payload,
            ]);

            $payloadQrisId = (string) ($payload['data']['qris_id'] ?? '');
            $expectedQrisId = (string) (config('services.qrisly.topup_qris_id') ?? env('QRISLY_TOPUP_QRIS_ID'));
            $expectiedQrisIdTransaction = (string) (config('services.qrisly.arletta_qris_id') ?? env('QRISLY_ARLETTA_QRIS_ID'));

            if ($payloadQrisId == $expectiedQrisIdTransaction) {
                Log::warning('Topup Balance Webhook: qris_id is for transaction', [
                    'payload_qris_id'  => $payloadQrisId,
                    'expected_qris_id' => $expectedQrisId,
                ]);

                $qrislyService = new \App\Services\QrislyService();
                $qrislyService->handleTransactionWebhook($payload);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'QRIS ID is for transaction, not topup.',
                ], 200);
            }

            if ($payloadQrisId !== $expectedQrisId) {
                Log::warning('Topup Balance Webhook: qris_id not match, ignore webhook', [
                    'payload_qris_id'  => $payloadQrisId,
                    'expected_qris_id' => $expectedQrisId,
                ]);

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'QRIS ID mismatch.',
                ], 200);
            }

            $qrisHistoryId = $payload['data']['qris_history_id'] ?? null;

            $topup = BalanceTopup::where('qris_history_id', (string) $qrisHistoryId)->where('topup_amount', $payload['data']['original_amount'])->first();
            if (! $topup) {
                Log::warning('Topup Balance Webhook: Data topup tidak ditemukan', [
                    'qris_history_id' => $qrisHistoryId,
                ]);

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'Topup record not found.',
                ], 200);
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
                Log::warning('Topup Balance Webhook: Event atau status tidak dikenal', [
                    'event'  => $event,
                    'status' => $dataStatus,
                ]);

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'Unrecognized event or status.',
                ], 200);
            }

            if ($targetStatus === 'success') {
                $processed = DB::transaction(function () use ($topup) {
                    // Lock row topup agar webhook paralel tidak memproses bersamaan
                    $lockedTopup = BalanceTopup::whereKey($topup->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Cek ulang status setelah mendapatkan lock
                    if ($lockedTopup->status === 'success') {
                        return false;
                    }

                    // Update status
                    $lockedTopup->update([
                        'status' => 'success',
                    ]);

                    // Tambahkan saldo
                    $balance = $lockedTopup->balance
                        ?: Balance::firstOrCreate([], ['balance' => 0]);

                    $balance->increment('balance', $lockedTopup->topup_amount);

                    // Insert BalanceLog satu kali
                    BalanceLog::create([
                        'balance_id'     => $balance->id,
                        'type'           => 'in',
                        'amount'         => $lockedTopup->topup_amount,
                        'transaction_id' => $lockedTopup->id,
                    ]);

                    return true;
                });

                if (! $processed) {
                    Log::info('Topup Balance Webhook: Duplicate webhook ignored', [
                        'topup_id' => $topup->id,
                    ]);

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Topup already processed.',
                    ], 200);
                }

                // Kirim Telegram setelah transaksi database berhasil commit
                try {
                    $telegramService = new TelegramService();

                    $amount = number_format($topup->topup_amount, 0, ',', '.');
                    $commission = number_format($topup->topup_amount * 0.2, 0, ',', '.');
                    $cleanMoney = number_format($topup->topup_amount * 0.8, 0, ',', '.');

                    $message = implode("\n", [
                        "💰 <b>TOPUP BALANCE SUCCESS</b>",
                        "",
                        "Topup dengan ID <b>#{$topup->id}</b> telah berhasil diproses.",
                        "",
                        "Nominal Topup: <b>Rp {$amount}</b>",
                        "Komisi 20%: <b>Rp {$commission}</b>",
                        "Clean Money: <b>Rp {$cleanMoney}</b>",
                        "",
                        "🤑 🤑 🤑",
                    ]);

                    $telegramService->sendMessage($message, 'HTML');
                } catch (\Throwable $e) {
                    Log::error('Topup Telegram notification failed', [
                        'topup_id' => $topup->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Topup Balance Webhook: Saldo berhasil ditambahkan', [
                    'topup_id'     => $topup->id,
                    'amount_added' => $topup->topup_amount,
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Topup processed and balance updated.',
                ], 200);
            }

            // Untuk status failed atau pending: perbarui status balance_topups saja
            $topup->update(['status' => $targetStatus]);

            Log::info("Topup Balance Webhook: Status topup diperbarui menjadi {$targetStatus}", [
                'topup_id' => $topup->id,
            ]);

            return response()->json([
                'status'  => 'updated',
                'message' => "Topup status updated to {$targetStatus}.",
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Topup Balance Webhook Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Internal webhook error occurred.',
            ], 200);
        }
    }
}
