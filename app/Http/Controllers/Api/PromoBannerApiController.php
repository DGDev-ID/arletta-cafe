<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\PromoBanner;
use Illuminate\Http\Request;

class PromoBannerApiController extends ApiBaseController
{
    /**
     * GET /api/promo-banners
     *
     * Mengembalikan banner promo yang aktif dan sesuai periode,
     * diurutkan berdasarkan sort_order ASC.
     */
    public function __invoke(Request $request)
    {
        try {
            $banners = PromoBanner::active()
                ->ordered()
                ->get(['id', 'title', 'image_url']);

            return $this->success($banners);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
