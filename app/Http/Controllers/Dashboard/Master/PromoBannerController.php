<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Helpers\S3Helper;
use App\Http\Controllers\Controller;
use App\Models\PromoBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoBannerController extends Controller
{
    public function index(Request $request)
    {
        $query = PromoBanner::query();

        // Search by title
        if ($request->has('search') && $request->search !== '') {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $banners = $query->orderBy('sort_order', 'asc')
            ->paginate(10)
            ->withQueryString();

        return inertia('master/promo-banner/Index', [
            'banners' => $banners,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return inertia('master/promo-banner/Create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Upload image to S3 (Supabase)
        $tempFileName = S3Helper::storeFileTemp($request->file('image'));
        S3Helper::storeFileToS3('promo-banners', $tempFileName);
        $imgUrl = S3Helper::getUrlFileS3('promo-banners', $tempFileName);
        S3Helper::removeFileTemp($tempFileName);

        PromoBanner::create([
            'title' => $validated['title'],
            'image_url' => $imgUrl,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('master.promo-banner.index')
            ->with('success', 'Banner promo berhasil ditambahkan.');
    }

    public function edit(string $id)
    {
        $banner = PromoBanner::findOrFail($id);

        return inertia('master/promo-banner/Edit', [
            'banner' => $banner,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $banner = PromoBanner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $data = [
            'title' => $validated['title'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ];

        // Upload new image if provided
        if ($request->hasFile('image')) {
            $tempFileName = S3Helper::storeFileTemp($request->file('image'));
            S3Helper::storeFileToS3('promo-banners', $tempFileName);
            $imgUrl = S3Helper::getUrlFileS3('promo-banners', $tempFileName);
            S3Helper::removeFileTemp($tempFileName);

            $data['image_url'] = $imgUrl;
        }

        $banner->update($data);

        return redirect()
            ->route('master.promo-banner.index')
            ->with('success', 'Banner promo berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $banner = PromoBanner::findOrFail($id);
        $banner->delete();

        return redirect()
            ->route('master.promo-banner.index')
            ->with('success', 'Banner promo berhasil dihapus.');
    }

    public function toggleStatus(string $id)
    {
        $banner = PromoBanner::findOrFail($id);
        $banner->update(['is_active' => !$banner->is_active]);

        return redirect()->back()->with('success', 'Status banner berhasil diubah.');
    }
}
