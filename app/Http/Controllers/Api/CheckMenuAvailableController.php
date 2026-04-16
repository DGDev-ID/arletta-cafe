<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MMenu;
use App\Services\MenuAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckMenuAvailableController extends ApiBaseController
{
    public function __construct(private readonly MenuAvailabilityService $menuAvailabilityService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'menu_id'  => ['required', 'integer', 'exists:m_menus,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $menu     = MMenu::find($request->integer('menu_id'));
        $quantity = $request->integer('quantity');

        $isAvailable = $this->menuAvailabilityService->checkAvailableMenu($menu, $quantity);

        if (!$isAvailable) {
            return $this->clientError(
                "Menu {$menu->name} dengan jumlah {$quantity} tidak bisa dipesan karena ketidaktersediaan bahan. Coba kurangi jumlah menu"
            );
        }

        return $this->success(null, 'Menu tersedia');
    }
}
