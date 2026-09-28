<?php

namespace Modules\Payment\Contracts;

use Modules\Payment\DTOs\PaymentDTO;

interface PaymentAdapterInterface
{
    public function pay(PaymentDTO $payment): array;
}
