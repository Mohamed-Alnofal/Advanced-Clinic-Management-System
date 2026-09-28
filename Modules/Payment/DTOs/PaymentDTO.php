<?php

namespace Modules\Payment\DTOs;

class PaymentDTO
{
    public function __construct(
        public float $amount,
        public string $paymentMethod,
        public int $userId,
        public string $referenceType,
        public int $referenceId,
        public string $idempotencyKey,
        public ?string $successUrl = null,
        public ?string $cancelUrl = null,
    ) {}
}