<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafe;
use App\Models\MGallery;
use App\Models\MMenu;
use Illuminate\Http\Request;

class LandingPageController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $cafe = MCafe::first();
            $menus = MMenu::with('category', 'promo')->where('cafe_id', $cafe->id)->get();
            $gallery = MGallery::all();
            $allCafe = MCafe::all();

            return $this->success([
                'menus' => $menus,
                'gallery' => $gallery,
                'all_cafe' => $allCafe,
            ]);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
