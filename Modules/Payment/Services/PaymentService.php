<?php

namespace Modules\Payment\Services;

use InvalidArgumentException;
use Modules\Payment\Adapters\PayLaterPaymentAdapter;
use Modules\Payment\Adapters\StripePaymentAdapter;
use Modules\Payment\Adapters\WalletPaymentAdapter;
use Modules\Payment\Contracts\PaymentAdapterInterface;
use Modules\Payment\DTOs\PaymentDTO;

class PaymentService
{
    public function __construct(
        private WalletPaymentAdapter $walletAdapter,
        private StripePaymentAdapter $stripeAdapter,
        private PayLaterPaymentAdapter $payLaterAdapter,
    ) {}

    public function pay(PaymentDTO $payment): array
    {
        return $this->resolveAdapter(
            $payment->paymentMethod
        )->pay($payment);
    }

    private function resolveAdapter(
        string $paymentMethod
    ): PaymentAdapterInterface {
        return match ($paymentMethod) {

            'wallet' => $this->walletAdapter,

            'stripe' => $this->stripeAdapter,

            'pay_later' => $this->payLaterAdapter,

            default => throw new InvalidArgumentException(
                'Unsupported payment method: ' . $paymentMethod
            ),
        };
    }
}