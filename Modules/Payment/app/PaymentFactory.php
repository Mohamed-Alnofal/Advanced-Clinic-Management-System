<?php

namespace Modules\Payment;

use Modules\Payment\Strategies\CashPayment;
use Modules\Payment\Strategies\WalletPayment;
use Modules\Payment\Strategies\StripeTopUpPayment;

class PaymentFactory
{
    public static function make(string $method)
    {
        return match ($method) {
            'cash' => new CashPayment(),
            'wallet' => app(WalletPayment::class),
            'stripe_topup' => new StripeTopUpPayment(),
        };
    }
}
