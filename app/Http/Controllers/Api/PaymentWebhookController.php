<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiBaseController;
use App\Services\XenditService;
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
            Log::info('Xendit Webhook Received', $payload);

            $result = XenditService::handleWebhook($payload, $request->header('X-CALLBACK-TOKEN'));
            if ($result === false) {
                return $this->clientError('Xendit callback error');
            }

            return $this->success('Webhook processed successfully');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
