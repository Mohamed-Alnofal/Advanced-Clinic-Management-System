<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Payment\DTOs\PaymentDTO;
use Modules\Payment\Services\PaymentService;

class PaymentController extends Controller
{
    public function pay(
        Request $request,
        PaymentService $service
    ) {
        $request->validate([
            'amount' => 'required|numeric|min:1',

            'payment_method' => 'required|in:wallet,stripe',

            'reference_type' => 'required|string|max:100',

            'reference_id' => 'required|integer',

            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $payment = new PaymentDTO(
                amount: (float) $request->amount,

                paymentMethod: $request->payment_method,

                userId: $request->user()->id,

                referenceType: $request->reference_type,

                referenceId: (int) $request->reference_id,

                metadata: [],

                idempotencyKey: $request->idempotency_key,
            );

            return response()->json(
                $service->pay($payment)
            );

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}