<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerFeedback;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerFeedbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'rating'         => ['required', 'integer', 'min:1', 'max:5'],
            'comment'        => ['nullable', 'string', 'max:1000'],
        ]);

        // Cegah feedback duplikat untuk transaksi yang sama
        $exists = CustomerFeedback::where('transaction_id', $validated['transaction_id'])->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Feedback untuk pesanan ini sudah pernah diberikan.',
            ], 422);
        }

        $feedback = CustomerFeedback::create([
            'transaction_id' => $validated['transaction_id'],
            'rating'         => $validated['rating'],
            'comment'        => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih atas feedback Anda!',
            'data'    => $feedback,
        ], 201);
    }
}
