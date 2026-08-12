<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\ThirdPartyChannel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ThirdPartyChannelController extends Controller
{
    public function index()
    {
        $channels = ThirdPartyChannel::orderBy('name')->get();

        return Inertia::render('master/third-party-channel/Index', [
            'channels' => $channels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100|unique:third_party_channels,name',
        ]);

        ThirdPartyChannel::create([
            'name'      => $validated['name'],
            'is_active' => true,
        ]);

        return redirect()->route('master.third-party-channel.index')
            ->with('success', 'Saluran pihak ketiga berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $channel = ThirdPartyChannel::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'required|string|max:100|unique:third_party_channels,name,' . $id,
        ]);

        $channel->update([
            'name'      => $validated['name'],
        ]);

        return redirect()->route('master.third-party-channel.index')
            ->with('success', 'Saluran pihak ketiga berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $channel = ThirdPartyChannel::findOrFail($id);
        $channel->delete();

        return redirect()->route('master.third-party-channel.index')
            ->with('success', 'Saluran pihak ketiga berhasil dihapus.');
    }

    public function toggleStatus($id)
    {
        $channel = ThirdPartyChannel::findOrFail($id);
        $channel->update(['is_active' => !$channel->is_active]);

        return redirect()->back()
            ->with('success', 'Status saluran berhasil diubah.');
    }

    public function manageMenus(Request $request, $id)
    {
        $channel = ThirdPartyChannel::findOrFail($id);
        
        $cafeId = $request->input('cafe_id');
        $cafes = \App\Models\MCafe::orderBy('name')->get();

        $menus = [];
        if ($cafeId) {
            $menus = \App\Models\MMenu::with(['category:id,name'])
                ->where('cafe_id', $cafeId)
                ->where('status', 'available')
                ->orderBy('name')
                ->get()
                ->map(function ($menu) use ($channel) {
                    $pivot = \App\Models\ThirdPartyChannelMenu::where('third_party_channel_id', $channel->id)
                        ->where('menu_id', $menu->id)
                        ->first();
                    
                    return [
                        'id'              => $menu->id,
                        'name'            => $menu->name,
                        'category_name'   => $menu->category ? $menu->category->name : '-',
                        'price'           => $menu->price,
                        'admin_fee'       => $pivot ? $pivot->admin_fee : null,
                        'is_manual_price' => $pivot ? (bool) $pivot->is_manual_price : false,
                        'override_price'  => $pivot ? $pivot->override_price : null,
                    ];
                });
        }

        return Inertia::render('master/third-party-channel/ManageMenus', [
            'channel' => $channel,
            'cafes' => $cafes,
            'menus' => $menus,
            'activeCafeId' => $cafeId,
        ]);
    }

    public function updateMenus(Request $request, $id)
    {
        $channel = ThirdPartyChannel::findOrFail($id);
        
        $validated = $request->validate([
            'cafe_id'                   => 'required|exists:m_cafes,id',
            'menus'                     => 'required|array',
            'menus.*.menu_id'           => 'required|exists:m_menus,id',
            'menus.*.admin_fee'         => 'nullable|numeric|min:0',
            'menus.*.is_manual_price'   => 'nullable|boolean',
            'menus.*.override_price'    => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['menus'] as $menuItem) {
            $isManual      = !empty($menuItem['is_manual_price']);
            $adminFee      = $menuItem['admin_fee'] ?? null;
            $overridePrice = $menuItem['override_price'] ?? null;

            // Jika mode manual aktif: wajib ada override_price
            // Jika mode normal: wajib ada admin_fee
            $hasValue = $isManual
                ? ($overridePrice !== null && $overridePrice !== '')
                : ($adminFee !== null && $adminFee !== '');

            if ($hasValue) {
                \App\Models\ThirdPartyChannelMenu::updateOrCreate(
                    [
                        'third_party_channel_id' => $channel->id,
                        'menu_id'                => $menuItem['menu_id'],
                    ],
                    [
                        'admin_fee'       => $isManual ? null : $adminFee,
                        'is_manual_price' => $isManual,
                        'override_price'  => $isManual ? $overridePrice : null,
                    ]
                );
            } else {
                \App\Models\ThirdPartyChannelMenu::where('third_party_channel_id', $channel->id)
                    ->where('menu_id', $menuItem['menu_id'])
                    ->delete();
            }
        }

        return redirect()->back()
            ->with('success', 'Harga admin per menu berhasil disimpan.');
    }
}
