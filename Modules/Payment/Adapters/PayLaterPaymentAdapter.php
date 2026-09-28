<?php

namespace Modules\Payment\Adapters;

use Modules\Payment\Contracts\PaymentAdapterInterface;
use Modules\Payment\DTOs\PaymentDTO;

class PayLaterPaymentAdapter implements PaymentAdapterInterface
{
    public function pay(PaymentDTO $payment): array
    {
        return [
            'success' => true,

            'status' => 'pending',

            'payment_method' => 'pay_later',

            'amount' => $payment->amount,

            'reference_type' => $payment->referenceType,

            'reference_id' => $payment->referenceId,

            'message' => 'Payment will be collected at the clinic.',
        ];
    }
}