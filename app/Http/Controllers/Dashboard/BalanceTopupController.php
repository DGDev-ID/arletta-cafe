<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BalanceTopup;
use App\Services\QrislyService;
use Inertia\Inertia;

class BalanceTopupController extends Controller
{
    public function show(int $id)
    {
        $topup = BalanceTopup::with('balance')->findOrFail($id);

        // Build QRIS string jika masih pending (untuk display QR code di frontend)
        // Kita simpan qris_string dari API awal — simpan ulang jika perlu
        // Untuk sekarang kita pass data topup ke view; frontend akan polling check endpoint
        return Inertia::render('balance/topup/Show', [
            'topup' => $topup,
        ]);
    }
}
