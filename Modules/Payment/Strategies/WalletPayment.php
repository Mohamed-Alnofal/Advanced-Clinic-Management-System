<?php

namespace Modules\Payment\Strategies;

use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Payment\Contracts\PaymentStrategy;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Services\WalletLedgerService;

class WalletPayment implements PaymentStrategy
{
    public function __construct(
        private WalletLedgerService $ledger,
    ) {}

    public function pay(Order $order, array $data = [])
    {
        return DB::transaction(function () use ($order) {
            $wallet = Wallet::where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (! $wallet || $wallet->balance < $order->total_price) {
                throw new \RuntimeException('Insufficient balance');
            }

            $this->ledger->debit(
                $wallet,
                (float) $order->total_price,
                'payment',
                'order-pay-'.$order->id
            );

            $order->update([
                'payment_method' => 'wallet',
                'payment_status' => 'paid',
            ]);

            return ['message' => 'Paid from wallet'];
        });
    }
}
