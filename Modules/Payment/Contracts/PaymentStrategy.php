<?php

namespace Modules\Payment\Contracts;

use Modules\Order\Models\Order;

interface PaymentStrategy
{
    public function pay(Order $order, array $data = []);
}