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
            if (in_array($topup->status, ['success', 'failed', 'pending'])) {
                return $this->success([
                    'status' => $topup->status,
                    'topup'  => $topup,
                ], 'Status topup berhasil diambil.');
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
