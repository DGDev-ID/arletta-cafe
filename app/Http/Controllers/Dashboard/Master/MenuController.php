<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Helpers\S3Helper;
use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MMenu;
use App\Models\MMenuCategory;
use App\Models\MMaterial;
use App\Models\MUnit;
use App\Models\MenuMaterial;
use App\Models\MenuPromo;
use App\Models\MenuSemiFinishedMaterial;
use App\Models\SemiFinishedMaterial;
use App\Models\UnitMaterialConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $allCafe = MCafe::select('id', 'name')->get();
        $cafeId = $request->query('cafe_id');
        $search = $request->query('search');
        $status = $request->query('status');
        
        $query = MMenu::with('cafe');
        if ($cafeId) {
            $query->where('cafe_id', $cafeId);
        }
        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }
        if ($status && in_array($status, ['available', 'unavailable'])) {
            $query->where('status', $status);
        }
        $data = $query->paginate(10)->withQueryString();

        return inertia('master/menu/Index', [
            'data'    => $data,
            'allCafe' => $allCafe,
            'search'  => $search ?? '',
            'status'  => $status ?? '',
            'cafe_id' => $cafeId ?? '',
        ]);
    }

    public function create()
    {
        return inertia('master/menu/Create', [
            'cafes'                 => MCafe::select('id', 'name')->get(),
            'categories'            => MMenuCategory::select('id', 'cafe_id', 'name')->get(),
            'materials'             => MMaterial::select('id', 'cafe_id', 'name', 'base_unit_id')->with('baseUnit:id,name')->get(),
            'units'                 => MUnit::select('id', 'name')->get(),
            'converters'            => UnitMaterialConverter::select('material_id', 'from_unit_id', 'to_unit_id')->get(),
            'semiFinishedMaterials' => SemiFinishedMaterial::with('unit')->select('id', 'cafe_id', 'name', 'base_unit_id')->get(),
            'menus'                 => MMenu::where('is_combo', false)->select('id', 'cafe_id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->has('start_time') && strlen($request->start_time) === 5) {
            $request->merge(['start_time' => $request->start_time . ':00']);
        }
        if ($request->has('end_time') && strlen($request->end_time) === 5) {
            $request->merge(['end_time' => $request->end_time . ':00']);
        }

        $rules = [
            'cafe_id'                                       => 'required|exists:m_cafes,id',
            'menu_category_id'                              => 'nullable|exists:m_menu_categories,id',
            'name'                                          => 'required|string|max:255',
            'description'                                   => 'nullable|string|max:1000',
            'image'                                         => 'nullable|image|max:5120',
            'price'                                         => 'required|numeric|min:0',
            'has_promo'                                     => 'boolean',
            'is_combo'                                      => 'boolean',
            'start_time'                                    => 'nullable|date_format:H:i:s',
            'end_time'                                      => 'nullable|date_format:H:i:s',
        ];

        if ($request->boolean('is_combo')) {
            $rules['combo_menus'] = 'nullable|array';
            $rules['combo_menus.*.menu_id'] = 'required|exists:m_menus,id';
            $rules['combo_menus.*.amount'] = 'required|integer|min:1';
            $rules['combo_groups'] = 'nullable|array';
            $rules['combo_groups.*.label'] = 'required|string|max:255';
            $rules['combo_groups.*.options'] = 'required|array|min:1';
            $rules['combo_groups.*.options.*.menu_id'] = 'required|exists:m_menus,id';
            $rules['combo_groups.*.options.*.amount'] = 'required|integer|min:1';
        } else {
            $rules['materials']                                     = 'nullable|array';
            $rules['materials.*.material_id']                       = 'required|exists:m_materials,id';
            $rules['materials.*.amount']                            = 'required|numeric|min:0.01';
            $rules['materials.*.unit_id']                            = 'required|exists:m_units,id';
            $rules['semi_finished_materials']                        = 'nullable|array';
            $rules['semi_finished_materials.*.semi_finished_material_id'] = 'required|exists:semi_finished_materials,id';
            $rules['semi_finished_materials.*.multiplier']           = 'required|numeric|min:0.01';
        }

        if ($request->boolean('has_promo')) {
            $rules['promo_type'] = 'required|in:discount_percent,discount_amount';
            $rules['promo_discount_amount'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request) {
            $imgUrl = null;
            if ($request->hasFile('image')) {
                $tempFileName = S3Helper::storeFileTemp($request->file('image'));
                $path = S3Helper::storeFileToS3('menus', $tempFileName);
                $imgUrl = S3Helper::getUrlFileS3('menus', $tempFileName);
                S3Helper::removeFileTemp($tempFileName);
            }

            $menu = MMenu::create([
                'cafe_id'          => $validated['cafe_id'],
                'menu_category_id' => $validated['menu_category_id'] ?? null,
                'name'             => $validated['name'],
                'description'      => $validated['description'] ?? null,
                'img_url'          => $imgUrl,
                'price'            => $validated['price'],
                'is_combo'         => $request->boolean('is_combo'),
                'start_time'       => isset($validated['start_time']) ? (strlen($validated['start_time']) == 5 ? $validated['start_time'].':00' : $validated['start_time']) : null,
                'end_time'         => isset($validated['end_time']) ? (strlen($validated['end_time']) == 5 ? $validated['end_time'].':00' : $validated['end_time']) : null,
            ]);

            if ($request->boolean('has_promo')) {
                MenuPromo::create([
                    'menu_id'         => $menu->id,
                    'type'            => $validated['promo_type'],
                    'discount_amount' => $validated['promo_discount_amount'],
                ]);
            }

            if ($request->boolean('is_combo')) {
                if (!empty($validated['combo_menus'])) {
                    foreach ($validated['combo_menus'] as $combo) {
                        \App\Models\MenuCombo::create([
                            'menu_id' => $menu->id,
                            'group_id' => null,
                            'combo_menu_id' => $combo['menu_id'],
                            'amount' => $combo['amount'],
                        ]);
                    }
                }
                if (!empty($validated['combo_groups'])) {
                    foreach ($validated['combo_groups'] as $sortOrder => $group) {
                        $comboGroup = \App\Models\MenuComboGroup::create([
                            'menu_id'    => $menu->id,
                            'label'      => $group['label'],
                            'sort_order' => $sortOrder,
                        ]);
                        foreach ($group['options'] as $option) {
                            \App\Models\MenuCombo::create([
                                'menu_id'       => $menu->id,
                                'group_id'      => $comboGroup->id,
                                'combo_menu_id' => $option['menu_id'],
                                'amount'        => $option['amount'],
                            ]);
                        }
                    }
                }
            } else {
                if (!empty($validated['materials'])) {
                    foreach ($validated['materials'] as $mat) {
                        MenuMaterial::create([
                            'menu_id'     => $menu->id,
                            'material_id' => $mat['material_id'],
                            'amount'      => $mat['amount'],
                            'unit_id'     => $mat['unit_id'],
                        ]);
                    }
                }

                if (!empty($validated['semi_finished_materials'])) {
                    foreach ($validated['semi_finished_materials'] as $sfm) {
                        MenuSemiFinishedMaterial::create([
                            'menu_id'                    => $menu->id,
                            'semi_finished_material_id'  => $sfm['semi_finished_material_id'],
                            'multiplier'                 => $sfm['multiplier'],
                        ]);
                    }
                }
            }
        });

        return redirect()
            ->route('master.menu.index', ['page' => request('page')])
            ->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = MMenu::with(['promo', 'menuMaterials', 'menuSemiFinishedMaterials', 'menuCombos', 'menuComboGroups.options'])->findOrFail($id);

        return inertia('master/menu/Edit', [
            'data'                  => $data,
            'cafes'                 => MCafe::select('id', 'name')->get(),
            'categories'            => MMenuCategory::select('id', 'cafe_id', 'name')->get(),
            'materials'             => MMaterial::select('id', 'cafe_id', 'name', 'base_unit_id')->with('baseUnit:id,name')->get(),
            'units'                 => MUnit::select('id', 'name')->get(),
            'converters'            => UnitMaterialConverter::select('material_id', 'from_unit_id', 'to_unit_id')->get(),
            'semiFinishedMaterials' => SemiFinishedMaterial::with('unit')->select('id', 'cafe_id', 'name', 'base_unit_id')->get(),
            'menus'                 => MMenu::where('is_combo', false)->select('id', 'cafe_id', 'name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $menu = MMenu::findOrFail($id);

        if ($request->has('start_time') && strlen($request->start_time) === 5) {
            $request->merge(['start_time' => $request->start_time . ':00']);
        }
        if ($request->has('end_time') && strlen($request->end_time) === 5) {
            $request->merge(['end_time' => $request->end_time . ':00']);
        }

        $rules = [
            'cafe_id'                                       => 'required|exists:m_cafes,id',
            'menu_category_id'                              => 'nullable|exists:m_menu_categories,id',
            'name'                                          => 'required|string|max:255',
            'description'                                   => 'nullable|string|max:1000',
            'image'                                         => 'nullable|image|max:5120',
            'price'                                         => 'required|numeric|min:0',
            'has_promo'                                     => 'boolean',
            'is_combo'                                      => 'boolean',
            'start_time'                                    => 'nullable|date_format:H:i:s',
            'end_time'                                      => 'nullable|date_format:H:i:s',
        ];

        if ($request->boolean('is_combo')) {
            $rules['combo_menus'] = 'nullable|array';
            $rules['combo_menus.*.menu_id'] = 'required|exists:m_menus,id';
            $rules['combo_menus.*.amount'] = 'required|integer|min:1';
            $rules['combo_groups'] = 'nullable|array';
            $rules['combo_groups.*.label'] = 'required|string|max:255';
            $rules['combo_groups.*.options'] = 'required|array|min:1';
            $rules['combo_groups.*.options.*.menu_id'] = 'required|exists:m_menus,id';
            $rules['combo_groups.*.options.*.amount'] = 'required|integer|min:1';
        } else {
            $rules['materials']                                     = 'nullable|array';
            $rules['materials.*.material_id']                       = 'required|exists:m_materials,id';
            $rules['materials.*.amount']                            = 'required|numeric|min:0.01';
            $rules['materials.*.unit_id']                            = 'required|exists:m_units,id';
            $rules['semi_finished_materials']                        = 'nullable|array';
            $rules['semi_finished_materials.*.semi_finished_material_id'] = 'required|exists:semi_finished_materials,id';
            $rules['semi_finished_materials.*.multiplier']           = 'required|numeric|min:0.01';
        }

        if ($request->boolean('has_promo')) {
            $rules['promo_type'] = 'required|in:discount_percent,discount_amount';
            $rules['promo_discount_amount'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($menu, $validated, $request) {
            $imgUrl = $menu->img_url;
            if ($request->hasFile('image')) {
                $tempFileName = S3Helper::storeFileTemp($request->file('image'));
                $path = S3Helper::storeFileToS3('menus', $tempFileName);
                $imgUrl = S3Helper::getUrlFileS3('menus', $tempFileName);
                S3Helper::removeFileTemp($tempFileName);
            }

            $menu->update([
                'cafe_id'          => $validated['cafe_id'],
                'menu_category_id' => $validated['menu_category_id'] ?? null,
                'name'             => $validated['name'],
                'description'      => $validated['description'] ?? null,
                'img_url'          => $imgUrl,
                'price'            => $validated['price'],
                'is_combo'         => $request->boolean('is_combo'),
                'start_time'       => isset($validated['start_time']) ? (strlen($validated['start_time']) == 5 ? $validated['start_time'].':00' : $validated['start_time']) : null,
                'end_time'         => isset($validated['end_time']) ? (strlen($validated['end_time']) == 5 ? $validated['end_time'].':00' : $validated['end_time']) : null,
            ]);

            $menu->promo()->delete();
            if ($request->boolean('has_promo')) {
                MenuPromo::create([
                    'menu_id'         => $menu->id,
                    'type'            => $validated['promo_type'],
                    'discount_amount' => $validated['promo_discount_amount'],
                ]);
            }

            $menu->menuMaterials()->delete();
            $menu->menuSemiFinishedMaterials()->delete();
            $menu->menuCombos()->delete();
            $menu->menuComboGroups()->delete();

            if ($request->boolean('is_combo')) {
                if (!empty($validated['combo_menus'])) {
                    foreach ($validated['combo_menus'] as $combo) {
                        \App\Models\MenuCombo::create([
                            'menu_id' => $menu->id,
                            'group_id' => null,
                            'combo_menu_id' => $combo['menu_id'],
                            'amount' => $combo['amount'],
                        ]);
                    }
                }
                if (!empty($validated['combo_groups'])) {
                    foreach ($validated['combo_groups'] as $sortOrder => $group) {
                        $comboGroup = \App\Models\MenuComboGroup::create([
                            'menu_id'    => $menu->id,
                            'label'      => $group['label'],
                            'sort_order' => $sortOrder,
                        ]);
                        foreach ($group['options'] as $option) {
                            \App\Models\MenuCombo::create([
                                'menu_id'       => $menu->id,
                                'group_id'      => $comboGroup->id,
                                'combo_menu_id' => $option['menu_id'],
                                'amount'        => $option['amount'],
                            ]);
                        }
                    }
                }
            } else {
                if (!empty($validated['materials'])) {
                    foreach ($validated['materials'] as $mat) {
                        MenuMaterial::create([
                            'menu_id'     => $menu->id,
                            'material_id' => $mat['material_id'],
                            'amount'      => $mat['amount'],
                            'unit_id'     => $mat['unit_id'],
                        ]);
                    }
                }

                if (!empty($validated['semi_finished_materials'])) {
                    foreach ($validated['semi_finished_materials'] as $sfm) {
                        MenuSemiFinishedMaterial::create([
                            'menu_id'                    => $menu->id,
                            'semi_finished_material_id'  => $sfm['semi_finished_material_id'],
                            'multiplier'                 => $sfm['multiplier'],
                        ]);
                    }
                }
            }
        });

        return redirect()
            ->route('master.menu.index', ['page' => request('page')])
            ->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $menu = MMenu::findOrFail($id);
        $menu->delete();

        return redirect()
            ->route('master.menu.index', ['page' => request('page')])
            ->with('success', 'Menu berhasil dihapus.');
    }

    public function toggleStatus($id)
    {
        $menu = MMenu::findOrFail($id);
        $menu->update([
            'status' => $menu->status === 'available' ? 'unavailable' : 'available',
        ]);

        return redirect()
            ->back()
            ->with('success', 'Status menu berhasil diubah.');
    }
}
