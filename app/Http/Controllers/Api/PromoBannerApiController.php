<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafe;
use App\Models\PromoBanner;
use Illuminate\Http\Request;

class PromoBannerApiController extends ApiBaseController
{
    /**
     * GET /api/promo-banners?cafe_id={unique_id}
     *
     * Mengembalikan banner promo yang aktif dan sesuai periode,
     * difilter berdasarkan cafe (unique_id) yang dikirim via query param,
     * diurutkan berdasarkan sort_order ASC.
     */
    public function __invoke(Request $request)
    {
        try {
            $cafeUniqueId = $request->query('cafe_id');

            $query = PromoBanner::active()->ordered();

            if ($cafeUniqueId) {
                // Cari cafe berdasarkan unique_id, lalu filter banner yang terdaftar di cafe tersebut
                $cafe = MCafe::where('unique_id', $cafeUniqueId)->first();

                if ($cafe) {
                    $query->forCafe($cafe->id);
                } else {
                    // Jika cafe tidak ditemukan, kembalikan array kosong
                    return $this->success([]);
                }
            } else {
                // Jika tidak ada cafe_id, tidak tampilkan banner manapun
                // (banner wajib terikat ke cafe tertentu)
                return $this->success([]);
            }

            $banners = $query->get(['id', 'title', 'image_url']);

            return $this->success($banners);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
