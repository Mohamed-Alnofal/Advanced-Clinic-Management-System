<?php

namespace Modules\Payment\Strategies;

use Modules\Order\Models\Order;
use Modules\Payment\Contracts\PaymentStrategy;

class CashPayment implements PaymentStrategy
{
    public function pay(Order $order, array $data = [])
    {
        $order->update([
            'payment_method' => 'cash',
            'payment_status' => 'pending'
        ]);

        return ['message' => 'Cash order created'];
    }
}