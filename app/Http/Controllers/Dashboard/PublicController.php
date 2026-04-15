<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MCafe;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function forPublic($cafeId)
    {
        $cafe = MCafe::where('unique_id', $cafeId)->firstOrFail();

        return Inertia::render('queue/ForPublic', [
            'cafe' => $cafe,
        ]);
    }

    public function forKitchen($cafeId)
    {
        $cafe = MCafe::where('unique_id', $cafeId)->firstOrFail();

        return Inertia::render('queue/ForKitchen', [
            'cafe' => $cafe,
        ]);
    }

    public function apiPublicQueue($cafeId): JsonResponse
    {
        $cafe = MCafe::where('unique_id', $cafeId)->firstOrFail();

        $transactions = Transaction::where('cafe_id', $cafe->id)
            ->where(function ($q) {
                $q->where('status', 'in_order');
            })
            ->with('table:id,name')
            ->select('id', 'cafe_id', 'table_id', 'cust_name', 'status', 'created_at', 'updated_at')
            ->oldest()
            ->get();

        return response()->json($transactions);
    }

    public function apiKitchenQueue($cafeId): JsonResponse
    {
        $cafe = MCafe::where('unique_id', $cafeId)->firstOrFail();

        $transactions = Transaction::where('cafe_id', $cafe->id)
            ->where(function ($q) {
                $q->where('status', 'in_order')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'success')
                         ->where('updated_at', '>=', now()->subMinutes(5));
                  });
            })
            ->with(['table:id,name', 'details.menu:id,name'])
            ->oldest()
            ->get();

        return response()->json($transactions);
    }
}
