<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\Balance;
use App\Models\BalanceLog;
use App\Models\BalanceTopup;
use App\Services\QrislyService;
use Illuminate\Http\JsonResponse;

class CheckBalanceTopupController extends ApiBaseController
{
    public function __invoke(int $id): JsonResponse
    {
        try {
            $topup = BalanceTopup::with('balance')->findOrFail($id);

            // Jika sudah final, langsung return tanpa cek ulang ke Qrisly
            if (in_array($topup->status, ['success', 'failed'])) {
                return $this->success([
                    'status' => $topup->status,
                    'topup'  => $topup,
                ], 'Status topup berhasil diambil.');
            }

            if (! $topup->qris_history_id) {
                return $this->clientError('QRIS history ID tidak ditemukan.');
            }

            $qrisly   = new QrislyService();
            $qrisData = $qrisly->checkPaymentStatus((int) $topup->id);

            if (! $qrisData) {
                return $this->clientError('Gagal mengambil status pembayaran dari Qrisly.');
            }

            $paymentStatus = $qrisData['payment_status'] ?? null;

            if ($paymentStatus === 'paid' || $paymentStatus === 'success') {
                // // Update topup status ke success
                // $topup->update(['status' => 'success']);

                // // Tambah balance
                // $balance = $topup->balance;
                // $balance->increment('balance', $topup->topup_amount);

                // // Catat balance log
                // BalanceLog::create([
                //     'balance_id'     => $balance->id,
                //     'type'           => 'in',
                //     'amount'         => $topup->topup_amount,
                //     'transaction_id' => $topup->id,
                // ]);

                return $this->success([
                    'status' => 'success',
                    'topup'  => $topup->fresh(),
                ], 'Pembayaran berhasil! Saldo telah ditambahkan.');
            }

            if ($paymentStatus === 'expired' || $paymentStatus === 'failed') {
                // $topup->update(['status' => 'failed']);

                return $this->success([
                    'status' => 'failed',
                    'topup'  => $topup->fresh(),
                ], 'Pembayaran gagal atau kadaluarsa.');
            }

            // Masih pending / unpaid
            return $this->success([
                'status' => 'pending',
                'topup'  => $topup,
            ], 'Menunggu pembayaran.');
        } catch (\Throwable $e) {
            return $this->serverError($e);
        }
    }
}
