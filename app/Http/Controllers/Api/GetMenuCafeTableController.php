<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Models\MCafe;
use App\Models\MCafeTable;
use App\Models\MMenuCategory;
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

            $menuCategory = MMenuCategory::with([
                'menus', 'children.menus'
            ])
                ->where('cafe_id', $cafe->id)
                ->whereNull('parent_id')
                ->get();

            return $this->success([
                'cafe' => $cafe,
                'table' => $table,
                'menu_categories' => $menuCategory,
            ]);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
