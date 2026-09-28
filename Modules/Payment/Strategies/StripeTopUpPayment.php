<?php

namespace Modules\Payment\Strategies;

use Stripe\Stripe;
use Stripe\Checkout\Session;
// use Modules\Wallet\Entities\WalletTransaction;
use Modules\Payment\Contracts\PaymentStrategy;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Models\WalletTransaction;

class StripeTopUpPayment implements PaymentStrategy
{
    public function pay($order = null, array $data = [])
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        // $wallet = auth()->user()->wallet;
        $wallet = Wallet::firstOrCreate(['user_id' => auth()->id()]);

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => $data['amount'],
            'type' => 'deposit',
            'status' => 'pending',
        ]);

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Wallet Top Up',
                    ],
                    'unit_amount' => $data['amount'] * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/wallet/success'),
            'cancel_url' => url('/wallet/cancel'),
            'metadata' => [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
            ]
        ]);

        $transaction->update([
            'stripe_session_id' => $session->id
        ]);

        return ['checkout_url' => $session->url];
    }
}