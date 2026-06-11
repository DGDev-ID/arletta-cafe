<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\CafePromo;
use App\Models\MCafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CafePromoController extends Controller
{
    public function index(Request $request)
    {
        $query = CafePromo::with('cafe');

        // Allow filtering by cafe_id
        if ($request->has('cafe_id') && $request->cafe_id !== 'all') {
            $query->where('cafe_id', $request->cafe_id);
        }

        // Search by promo code
        if ($request->has('search')) {
            $query->where('promo_code', 'like', '%' . $request->search . '%');
        }

        $promos = $query->latest()->paginate(10)->withQueryString();
        $cafes = MCafe::all();

        return inertia('master/cafe-promo/Index', [
            'promos' => $promos,
            'cafes' => $cafes,
            'filters' => $request->only(['cafe_id', 'search']),
        ]);
    }

    public function create()
    {
        $cafes = MCafe::all();
        return inertia('master/cafe-promo/Create', [
            'cafes' => $cafes,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'promo_code' => strtoupper($request->promo_code),
        ]);
        $validator = Validator::make($request->all(), [
            'cafe_id' => 'required|exists:m_cafes,id',
            'promo_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cafe_promos')->where(function ($query) use ($request) {
                    return $query->where('cafe_id', $request->cafe_id);
                }),
            ],
            'type' => 'required|in:discount_percent,discount_amount',
            'value' => 'required|numeric|min:0',
        ]);
        // $validator['promo_code'] = strtoupper($validator['promo_code']);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        CafePromo::create($validator->validated());

        return redirect()->route('master.cafe-promo.index')->with('success', 'Promo berhasil ditambahkan');
    }

    public function edit(string $id)
    {
        $promo = CafePromo::findOrFail($id);
        $cafes = MCafe::all();
        return inertia('master/cafe-promo/Edit', [
            'promo' => $promo,
            'cafes' => $cafes,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $promo = CafePromo::findOrFail($id);
        $request->merge([
            'promo_code' => strtoupper($request->promo_code),
        ]);
        $validator = Validator::make($request->all(), [
            'cafe_id' => 'required|exists:m_cafes,id',
            'promo_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cafe_promos')->where(function ($query) use ($request) {
                    return $query->where('cafe_id', $request->cafe_id);
                })->ignore($promo->id),
            ],
            'type' => 'required|in:discount_percent,discount_amount',
            'value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $promo->update($validator->validated());

        return redirect()->route('master.cafe-promo.index')->with('success', 'Promo berhasil diubah');
    }

    public function destroy(string $id)
    {
        $promo = CafePromo::findOrFail($id);
        $promo->delete();

        return redirect()->route('master.cafe-promo.index')->with('success', 'Promo berhasil dihapus');
    }

    public function toggleStatus(string $id)
    {
        $promo = CafePromo::findOrFail($id);
        $promo->update(['status' => !$promo->status]);

        return redirect()->back()->with('success', 'Status promo berhasil diubah');
    }
}
