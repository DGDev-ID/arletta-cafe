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

            // =========================================================================
            // Langkah 1 — Validasi domain pengirim
            // Pengirim yang diizinkan: core.cafellio.my.id
            // =========================================================================
            if (! $this->isValidSenderDomain($request)) {
                Log::warning('Topup Balance Webhook: Pengirim domain tidak diizinkan', [
                    'ip'      => $request->ip(),
                    'origin'  => $request->headers->get('origin'),
                    'referer' => $request->headers->get('referer'),
                ]);

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'Sender domain not authorized.',
                ], 200);
            }

            // =========================================================================
            // Langkah 2 — Validasi secret pada header
            // Header: x-thirdparty-key
            // Validasi dengan Hash::check($xThirdPartyKey, config('services.thirdparty.secret_key'))
            // =========================================================================
            $xThirdPartyKey = $request->header('x-thirdparty-key');
            $configuredSecret = (string) config('services.thirdparty.secret_key');

            if (empty($xThirdPartyKey) || empty($configuredSecret) || ! $this->validateSecretKey($xThirdPartyKey, $configuredSecret)) {
                Log::warning('Topup Balance Webhook: Secret key header invalid atau tidak tersedia');

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'Invalid or missing secret key.',
                ], 200);
            }

            // =========================================================================
            // Langkah 3 — Validasi qris_id
            // Periksa apakah payload.data.qris_id sama dengan QRISLY_TOPUP_QRIS_ID
            // =========================================================================
            $payloadQrisId = (string) ($payload['data']['qris_id'] ?? '');
            $expectedQrisId = (string) (config('services.qrisly.topup_qris_id') ?? env('QRISLY_TOPUP_QRIS_ID'));

            if (empty($payloadQrisId) || empty($expectedQrisId) || $payloadQrisId !== $expectedQrisId) {
                Log::warning('Topup Balance Webhook: qris_id tidak cocok', [
                    'payload_qris_id'  => $payloadQrisId,
                    'expected_qris_id' => $expectedQrisId,
                ]);

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'QRIS ID mismatch.',
                ], 200);
            }

            // =========================================================================
            // Langkah 4 — Cari data top up
            // Cari record pada tabel balance_topups dengan qris_history_id
            // =========================================================================
            $qrisHistoryId = $payload['data']['qris_history_id'] ?? null;
            if (empty($qrisHistoryId)) {
                Log::warning('Topup Balance Webhook: qris_history_id kosong pada payload');

                return response()->json([
                    'status'  => 'ignored',
                    'message' => 'Missing qris_history_id.',
                ], 200);
            }

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

            // =========================================================================
            // Langkah 5 — Perbarui status top up
            // Pemetaan status provider ke status internal aplikasi
            // =========================================================================
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

            // =========================================================================
            // Langkah 6 — Proses saldo jika berhasil
            // =========================================================================
            if ($targetStatus === 'success') {
                // Idempotensi: jika sudah success sebelumnya, jangan tambah saldo lagi
                if ($topup->status === 'success') {
                    Log::info('Topup Balance Webhook: Topup sudah berstatus success sebelumnya (idempotent)', [
                        'topup_id'        => $topup->id,
                        'qris_history_id' => $qrisHistoryId,
                    ]);

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Topup already processed.',
                    ], 200);
                }

                DB::transaction(function () use ($topup) {
                    // Perbarui status record balance_topups
                    $topup->update(['status' => 'success']);

                    // Perbarui saldo pada tabel balances (gunakan nominal topup yang tersimpan di DB sebagai sumber kebenaran)
                    $balance = $topup->balance ?: Balance::firstOrCreate([], ['balance' => 0]);
                    $balance->increment('balance', $topup->topup_amount);

                    // Catat riwayat ke balance_logs
                    BalanceLog::create([
                        'balance_id'     => $balance->id,
                        'type'           => 'in',
                        'amount'         => $topup->topup_amount,
                        'transaction_id' => $topup->id,
                    ]);
                });

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

            // Selalu kembalikan HTTP 200 untuk webhook handler agar tidak terjadi retry loop berbahaya jika diinginkan
            return response()->json([
                'status'  => 'error',
                'message' => 'Internal webhook error occurred.',
            ], 200);
        }
    }

    /**
     * Langkah 1 Helper: Validasi domain pengirim request
     */
    protected function isValidSenderDomain(Request $request): bool
    {
        $allowedDomain = config('services.thirdparty.allowed_domain', 'core.cafellio.my.id');

        // Di environment local/testing, izinkan localhost untuk mempermudah testing
        if (app()->environment('local', 'testing') && in_array($request->ip(), ['127.0.0.1', '::1'])) {
            return true;
        }

        $clientIp = $request->ip();

        // 1. Cek reverse DNS IP pengirim
        $hostname = @gethostbyaddr($clientIp);
        if ($hostname && ($hostname === $allowedDomain || str_ends_with($hostname, '.' . $allowedDomain))) {
            return true;
        }

        // 2. Cek resolved IPs dari allowed domain
        $resolvedIps = @gethostbynamel($allowedDomain);
        if (is_array($resolvedIps) && in_array($clientIp, $resolvedIps, true)) {
            return true;
        }

        // 3. Cek Header Origin / Referer
        foreach ([$request->headers->get('origin'), $request->headers->get('referer')] as $url) {
            if ($url) {
                $host = parse_url($url, PHP_URL_HOST);
                if ($host === $allowedDomain) {
                    return true;
                }
            }
        }

        // 4. Cek header X-Forwarded-Host atau Forwarded jika berada di belakang reverse proxy
        $forwardedHost = $request->headers->get('x-forwarded-host');
        if ($forwardedHost && ($forwardedHost === $allowedDomain || str_contains($forwardedHost, $allowedDomain))) {
            return true;
        }

        return false;
    }

    /**
     * Langkah 2 Helper: Validasi header x-thirdparty-key
     * Mendukung Hash::check jika configured secret adalah bcrypt/argon hash,
     * serta fallback perbandingan aman jika secret disimpan plaintext.
     */
    protected function validateSecretKey(string $providedKey, string $configuredSecret): bool
    {
        // Coba Hash::check terlebih dahulu sesuai instruksi flow
        try {
            if (Hash::check($providedKey, $configuredSecret)) {
                return true;
            }
        } catch (\Throwable $e) {
            // Abaikan error jika format bukan hash valid
        }

        // Fallback aman jika THIRDPARTY_SECRET_KEY di-set plaintext di .env
        return hash_equals($configuredSecret, $providedKey);
    }
}

