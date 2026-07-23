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
            'admin_fee' => 'required|numeric|min:0',
        ]);

        ThirdPartyChannel::create([
            'name'      => $validated['name'],
            'admin_fee' => $validated['admin_fee'],
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
            'admin_fee' => 'required|numeric|min:0',
        ]);

        $channel->update([
            'name'      => $validated['name'],
            'admin_fee' => $validated['admin_fee'],
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
}
