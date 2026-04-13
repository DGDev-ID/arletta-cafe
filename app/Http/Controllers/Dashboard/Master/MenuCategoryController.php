<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\MMenuCategory;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    public function index(Request $request)
    {
        $allCafe = MCafe::select('id', 'name')->get();
        $cafeId = $request->query('cafe_id');
        $query = MMenuCategory::with('cafe', 'parent');
        if ($cafeId) {
            $query->where('cafe_id', $cafeId);
        }
        $data = $query->paginate(10)->withQueryString();

        return inertia('master/menu-category/Index', [
            'data'    => $data,
            'allCafe' => $allCafe,
        ]);
    }

    public function create()
    {
        return inertia('master/menu-category/Create', [
            'cafes'      => MCafe::select('id', 'name')->get(),
            'categories' => MMenuCategory::select('id', 'cafe_id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cafe_id'     => 'required|exists:m_cafes,id',
            'name'        => 'required|string|max:255',
            'parent_id'   => 'nullable|exists:m_menu_categories,id',
            'description' => 'nullable|string|max:1000',
        ]);

        MMenuCategory::create($validated);

        return redirect()
            ->route('master.menu-category.index')
            ->with('success', 'Kategori menu berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = MMenuCategory::findOrFail($id);

        return inertia('master/menu-category/Edit', [
            'data'       => $data,
            'cafes'      => MCafe::select('id', 'name')->get(),
            'categories' => MMenuCategory::select('id', 'cafe_id', 'name')
                ->where('id', '!=', $id)
                ->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = MMenuCategory::findOrFail($id);

        $validated = $request->validate([
            'cafe_id'     => 'required|exists:m_cafes,id',
            'name'        => 'required|string|max:255',
            'parent_id'   => 'nullable|exists:m_menu_categories,id',
            'description' => 'nullable|string|max:1000',
        ]);

        $category->update($validated);

        return redirect()
            ->route('master.menu-category.index')
            ->with('success', 'Kategori menu berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $category = MMenuCategory::findOrFail($id);
        $category->delete();

        return redirect()
            ->route('master.menu-category.index')
            ->with('success', 'Kategori menu berhasil dihapus.');
    }
}
