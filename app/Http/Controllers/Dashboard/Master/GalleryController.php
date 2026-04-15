<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Helpers\S3Helper;
use App\Http\Controllers\Controller;
use App\Models\MGallery;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index()
    {
        $data = MGallery::latest()->paginate(10);

        return inertia('master/gallery/Index', [
            'data' => $data,
        ]);
    }

    public function create()
    {
        return inertia('master/gallery/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $tempFileName = S3Helper::storeFileTemp($request->file('image'));
        S3Helper::storeFileToS3('gallery', $tempFileName);
        $imgUrl = S3Helper::getUrlFileS3('gallery', $tempFileName);
        S3Helper::removeFileTemp($tempFileName);

        MGallery::create([
            'img_url' => $imgUrl,
        ]);

        return redirect()
            ->route('master.gallery.index')
            ->with('success', 'Gambar berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $data = MGallery::findOrFail($id);

        return inertia('master/gallery/Edit', [
            'data' => $data,
        ]);
    }

    public function update(Request $request, $id)
    {
        $gallery = MGallery::findOrFail($id);

        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $tempFileName = S3Helper::storeFileTemp($request->file('image'));
        S3Helper::storeFileToS3('gallery', $tempFileName);
        $imgUrl = S3Helper::getUrlFileS3('gallery', $tempFileName);
        S3Helper::removeFileTemp($tempFileName);

        $gallery->update([
            'img_url' => $imgUrl,
        ]);

        return redirect()
            ->route('master.gallery.index')
            ->with('success', 'Gambar berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $gallery = MGallery::findOrFail($id);
        $gallery->delete();

        return redirect()
            ->route('master.gallery.index')
            ->with('success', 'Gambar berhasil dihapus.');
    }
}
