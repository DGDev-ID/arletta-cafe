<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\MUnit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $data = MUnit::paginate(10);
        return inertia('master/unit/Index', [
            'data' => $data,
        ]);
    }

    public function create()
    {
        return inertia('master/unit/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:m_units,name',
        ]);

        MUnit::create([
            'name' => $request->name,
        ]);

        return redirect()
            ->route('master.unit.index')
            ->with('success', 'Unit berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = MUnit::findOrFail($id);
        return inertia('master/unit/Edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        $unit = MUnit::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:m_units,name,' . $unit->id,
        ]);

        $unit->update([
            'name' => $request->name,
        ]);

        return redirect()
            ->route('master.unit.index')
            ->with('success', 'Unit berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $unit = MUnit::findOrFail($id);
        $unit->delete();

        return redirect()
            ->route('master.unit.index')
            ->with('success', 'Unit berhasil dihapus.');
    }
}
