<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends ApiBaseController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $payload = $request->all();
            Log::info('Midtrans Webhook Received', $payload);

            $result = MidtransService::handleWebhook($payload);
            if ($result === false) {
                return $this->clientError('Midtrans callback error');
            }

            return $this->success('Webhook processed successfully');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
