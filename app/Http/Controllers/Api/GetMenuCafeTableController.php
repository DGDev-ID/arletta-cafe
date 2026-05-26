<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MMenuCategory;
use App\Models\Transaction;
use App\Services\MenuAvailabilityService;
use Illuminate\Http\Request;

class GetMenuCafeTableController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $cafeId = $request->query('cafe_id');
            $tableId = $request->query('table_id');

            if (!$cafeId || !$tableId) {
                return $this->clientError('cafe_id and table_id are required');
            }

            $cafe = MCafe::where('unique_id', $cafeId)->first();
            if (!$cafe) {
                return $this->clientError('Cafe not found');
            }

            $table = MCafeTable::where('cafe_id', $cafe->id)->find($tableId);
            if (!$table) {
                return $this->clientError('Table not found');
            }

            $availabilityService = new MenuAvailabilityService();

            $menuCategories = MMenuCategory::with([
                'menus.menuMaterials.material.variants',
                'menus.menuSemiFinishedMaterials.semiFinishedMaterial.details',
                'children.menus.menuMaterials.material.variants',
                'children.menus.menuSemiFinishedMaterials.semiFinishedMaterial.details',
            ])
                ->where('cafe_id', $cafe->id)
                ->whereNull('parent_id')
                ->get();

            // Filter menus berdasarkan ketersediaan material
            $menuCategories->each(function ($category) use ($availabilityService) {
                $this->filterAvailableMenus($category, $availabilityService);

                if ($category->relationLoaded('children')) {
                    $category->children->each(function ($child) use ($availabilityService) {
                        $this->filterAvailableMenus($child, $availabilityService);
                    });

                    // Hapus child categories yang tidak punya menu tersedia
                    $category->setRelation(
                        'children',
                        $category->children->filter(fn($child) => $child->menus->isNotEmpty())
                    );
                }
            });

            // Hapus parent categories yang tidak punya menu & children tersedia
            $menuCategories = $menuCategories->filter(function ($category) {
                $hasMenus = $category->menus->isNotEmpty();
                $hasChildren = $category->relationLoaded('children') && $category->children->isNotEmpty();
                return $hasMenus || $hasChildren;
            })->values();

            // Get open transaction for this table (if any)
            $transaction = Transaction::with(['details.menu'])
                ->where('table_id', $table->id)
                ->where('is_open_bill', 1)
                ->where('status', 'pending')
                ->orderBy('id', 'desc')
                ->first();
            
            $transformMenu = function ($menu) {
    $selectableMaterials = $menu->menuMaterials
        ->filter(fn($mm) => $mm->material->type === 'selectable')
        ->map(fn($mm) => [
            'material_id'   => $mm->material->id,
            'material_name' => $mm->material->name,
            'variants'      => $mm->material->variants
                ->map(fn($v) => [
                    'id'            => $v->id,
                    'material_id'   => $v->material_id,
                    'name'          => $v->name,
                    'stock'         => $v->stock,
                    'minimum_stock' => $v->minimum_stock,
                ])->values(),
        ])->values();

    $menu->selectable_materials = $selectableMaterials;
    return $menu;
};

$menuCategories->each(function ($category) use ($transformMenu) {
    $category->menus->each($transformMenu);
    if ($category->relationLoaded('children')) {
        $category->children->each(function ($child) use ($transformMenu) {
            $child->menus->each($transformMenu);
        });
    }
});

            return $this->success([
                'cafe' => $cafe,
                'table' => $table,
                'transaction' => $transaction ? $transaction : null,
                'is_transaction_pending' => $transaction ? ($transaction->status === 'pending') : false,
                'menu_categories' => $menuCategories,
            ]);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    /**
     * Filter menus pada category: hanya simpan menu yang materialnya tersedia.
     * Menu tanpa material/SFM (tidak terikat stok) tetap ditampilkan.
     */
    private function filterAvailableMenus($category, MenuAvailabilityService $service): void
    {
        if (!$category->relationLoaded('menus')) {
            return;
        }

        $available = $category->menus->filter(function ($menu) use ($service) {
            $hasMaterials = $menu->menuMaterials->isNotEmpty();
            $hasSfm = $menu->menuSemiFinishedMaterials->isNotEmpty();

            // Menu tanpa resep material → selalu tampil
            if (!$hasMaterials && !$hasSfm) {
                return true;
            }

            return $service->checkAvailableMenu($menu, 1);
        });

        $category->setRelation('menus', $available->values());
    }
}
