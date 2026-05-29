<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MMenu;
use App\Services\MenuAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckMenusAvailableController extends ApiBaseController
{
    public function __construct(private readonly MenuAvailabilityService $menuAvailabilityService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'items'             => ['required', 'array', 'min:1'],
            'items.*.menu_id'   => ['required', 'integer', 'exists:m_menus,id'],
            'items.*.quantity'  => ['required', 'integer', 'min:1'],
            'items.*.selected_variants' => ['nullable', 'array'],
            'items.*.selected_variants.*.material_id' => ['required_with:items.*.selected_variants', 'integer'],
            'items.*.selected_variants.*.variant_id' => ['required_with:items.*.selected_variants', 'integer'],
        ]);

        $items = collect($request->input('items'))->map(fn ($item) => [
            'menu'     => MMenu::find($item['menu_id']),
            'quantity' => (int) $item['quantity'],
            'selected_variants' => $item['selected_variants'] ?? [],
        ])->all();

        $unavailableMenuNames = $this->menuAvailabilityService->checkAvailableMenus($items);

        if (!empty($unavailableMenuNames)) {
            $menuList = implode(', ', $unavailableMenuNames);

            return $this->clientError(
                "Beberapa menu tidak dapat dipesan karena ketidaktersediaan bahan: {$menuList}. Coba kurangi jumlah pesanan.",
                ['unavailable_menus' => $unavailableMenuNames]
            );
        }

        return $this->success(null, 'Semua menu tersedia');
    }
}
