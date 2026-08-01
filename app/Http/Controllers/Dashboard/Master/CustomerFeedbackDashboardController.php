<?php

namespace App\Http\Controllers\Dashboard\Master;

use App\Http\Controllers\Controller;
use App\Models\CustomerFeedback;
use App\Models\MCafe;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerFeedbackDashboardController extends Controller
{
    public function index(Request $request)
    {
        $cafeId = $request->filled('cafe_id') ? (int) $request->cafe_id : null;

        $feedbacks = CustomerFeedback::with(['transaction:id,unique_code,cafe_id,cust_name,table_id,created_at', 'transaction.cafe:id,name', 'transaction.table:id,name'])
            ->when($cafeId, fn ($q) => $q->whereHas('transaction', fn ($tq) => $tq->where('cafe_id', $cafeId)))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $averageRating = CustomerFeedback::when($cafeId, fn ($q) => $q->whereHas('transaction', fn ($tq) => $tq->where('cafe_id', $cafeId)))
            ->avg('rating');

        $ratingDistribution = CustomerFeedback::when($cafeId, fn ($q) => $q->whereHas('transaction', fn ($tq) => $tq->where('cafe_id', $cafeId)))
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get()
            ->keyBy('rating')
            ->map(fn ($row) => (int) $row->count);

        $totalFeedbacks = CustomerFeedback::when($cafeId, fn ($q) => $q->whereHas('transaction', fn ($tq) => $tq->where('cafe_id', $cafeId)))->count();

        $cafes = MCafe::orderBy('name')->get(['id', 'name']);

        return Inertia::render('master/customer-feedback/Index', [
            'feedbacks'          => $feedbacks,
            'averageRating'      => $averageRating ? round((float) $averageRating, 1) : null,
            'totalFeedbacks'     => $totalFeedbacks,
            'ratingDistribution' => $ratingDistribution,
            'cafes'              => $cafes,
            'activeCafeId'       => $cafeId,
        ]);
    }
}
