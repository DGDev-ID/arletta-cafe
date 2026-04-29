<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MCafe;
use App\Models\CafePromo;

class CheckPromoCodeController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'promo_code' => ['required', 'string'],
            'cafe_id' => ['required', 'exists:m_cafes,unique_id']
        ]); 

        $cafe = MCafe::where('unique_id', $validated['cafe_id'])->first();

        if (!$cafe) {
            return $this->clientError('Cafe tidak ditemukan');
        }

        $cafePromo = CafePromo::where('promo_code', $validated['promo_code'])
            ->where('cafe_id', $cafe->id)
            ->where('status', true)
            ->first();

        if (!$cafePromo) {
            return $this->clientError('Kode promo tidak ditemukan atau tidak aktif');
        }

        return $this->successResponse($cafePromo);
    }
}
