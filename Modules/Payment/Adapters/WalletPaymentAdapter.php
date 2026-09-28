<?php

namespace Modules\Payment\Adapters;

use Modules\Payment\Contracts\PaymentAdapterInterface;
use Modules\Payment\DTOs\PaymentDTO;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Services\WalletHoldService;

class WalletPaymentAdapter implements PaymentAdapterInterface
{
    public function __construct(
        private WalletHoldService $walletHoldService,
    ) {}

    public function pay(PaymentDTO $payment): array
    {
        $wallet = Wallet::firstOrCreate(
            [
                'user_id' => $payment->userId,
            ],
            [
                'balance' => 0,
            ]
        );

        $hold = $this->walletHoldService->createHold(
            wallet: $wallet,
            amount: $payment->amount,
            appointmentId: $payment->referenceId,
            idempotencyKey: $payment->idempotencyKey,
        );

        return [
            'success' => true,

            'status' => 'paid',

            'payment_method' => 'wallet',

            'amount' => $payment->amount,

            'reference_type' => $payment->referenceType,

            'reference_id' => $payment->referenceId,

            'wallet_hold_id' => $hold->id,

            'message' => 'Payment held successfully in wallet.',
        ];
    }
}