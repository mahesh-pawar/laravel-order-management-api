<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentWebhookRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentService $paymentService)
    {
    }

    public function __invoke(PaymentWebhookRequest $request): JsonResponse
    {
        $processed = $this->paymentService->process($request->validated());

        return response()->json([
            'message' => $processed ? 'Payment processed.' : 'Payment already processed.',
        ]);
    }
}
