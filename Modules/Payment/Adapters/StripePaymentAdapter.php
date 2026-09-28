<?php

namespace Modules\Payment\Adapters;

use Stripe\Checkout\Session;
use Stripe\Stripe;
use Modules\Payment\Contracts\PaymentAdapterInterface;
use Modules\Payment\DTOs\PaymentDTO;

class StripePaymentAdapter implements PaymentAdapterInterface
{
    public function pay(PaymentDTO $payment): array
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            throw new \RuntimeException(
                'Stripe is not configured. Set STRIPE_SECRET in the server environment.'
            );
        }

        Stripe::setApiKey($secret);

        $amountCents = (int) round($payment->amount * 100);
        if ($amountCents < 50) {
            throw new \RuntimeException(
                'Stripe amount is too small (minimum 0.50).'
            );
        }

        $successUrl = $payment->successUrl
            ?? $this->stripeReturnUrl('/api/payment/stripe/success?session_id={CHECKOUT_SESSION_ID}');

        $cancelUrl = $payment->cancelUrl
            ?? $this->stripeReturnUrl('/api/payment/stripe/cancel');

        // Stripe metadata values MUST be strings; bad types can break Checkout UI.
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Medical Appointment Payment',
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => (string) $payment->userId,
                'reference_type' => (string) $payment->referenceType,
                'reference_id' => (string) $payment->referenceId,
                'idempotency_key' => (string) ($payment->idempotencyKey ?? ''),
            ],
        ]);

        return [
            'success' => true,

            'status' => 'pending',

            'payment_method' => 'stripe',

            'amount' => $payment->amount,

            'reference_type' => $payment->referenceType,

            'reference_id' => $payment->referenceId,

            'stripe_session_id' => $session->id,

            'checkout_url' => $session->url,

            'message' => 'Stripe checkout created successfully.',
        ];
    }

    /**
     * Stripe requires absolute https return URLs in production.
     */
    private function stripeReturnUrl(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            $base = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        }

        if (
            str_starts_with($base, 'http://')
            && ! app()->environment(['local', 'testing'])
        ) {
            $base = 'https://'.substr($base, 7);
        }

        return $base.'/'.ltrim($path, '/');
    }
}