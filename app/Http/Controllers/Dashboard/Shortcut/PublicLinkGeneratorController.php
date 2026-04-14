<?php

namespace App\Http\Controllers\Dashboard\Shortcut;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use Inertia\Inertia;

class PublicLinkGeneratorController extends Controller
{
    public function index()
    {
        $cafes = MCafe::select('id', 'unique_id', 'name', 'address')->orderBy('name')->get();

        return Inertia::render('shortcut/public-link-generator/Index', [
            'cafes'   => $cafes,
            'baseUrl' => rtrim(config('app.url'), '/'),
        ]);
    }
}
