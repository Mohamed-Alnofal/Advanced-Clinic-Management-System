<?php

namespace Modules\Wallet\Services;

use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Models\WalletTransaction;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Illuminate\Http\Request;

use Modules\Wallet\Services\WalletLedgerService;
use Stripe\Webhook;
use Illuminate\Support\Facades\DB;
class WalletTopUpService
{
    public function __construct(
        private WalletLedgerService $ledger,
    ) {}

 public function createWallet(User $user, string $type = 'user'): Wallet
    {
        return Wallet::firstOrCreate(
            [
                'user_id' => $user->id, // 👈 من auth
            ],
            [
                'balance' => 0,
            ]
        );
    }


    public function createCheckout($user, float $amount): array
    {
        $wallet = Wallet::firstOrCreate([
            'user_id' => $user->id,
        ]);

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'type' => 'deposit',
            'status' => 'pending',
        ]);

        // Demo/local mode: credit immediately without Stripe.
        if (config('wallet.instant_topup')) {
            $wallet = $this->ledger->creditDeposit($transaction);

            return [
                'instant' => true,
                'balance' => $wallet->balance,
            ];
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Wallet Top Up',
                    ],
                    'unit_amount' => $amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',

            // 'success_url' => url('/api/wallet/success'),
            'success_url' =>
            url('/api/wallet/stripe-success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/api/wallet/cancel'),

            'metadata' => [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
            ]
        ]);

        // 4. save session id
        $transaction->update([
            'stripe_session_id' => $session->id
        ]);

        return [
            'checkout_url' => $session->url,
        ];
    }

    /**
     * Credit any pending deposits whose Stripe session is already paid.
     */
    public function syncPendingDeposits(Wallet $wallet): void
    {
        if (! config('services.stripe.secret')) {
            return;
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $pending = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('status', 'pending')
            ->where('type', 'deposit')
            ->whereNotNull('stripe_session_id')
            ->get();

        foreach ($pending as $transaction) {
            try {
                $session = Session::retrieve($transaction->stripe_session_id);
                if ($session->payment_status === 'paid') {
                    $this->ledger->creditDeposit($transaction);
                }
            } catch (\Throwable) {
                // Skip failed Stripe lookups during sync.
            }
        }
    }

public function handle(Request $request)
    {
        $endpointSecret = config('services.stripe.webhook_secret')
            ?? config('services.stripe.webhook');

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid webhook'], 400);
        }

        // Event-level idempotency (Stripe retries).
        try {
            DB::table('stripe_webhook_events')->insert([
                'event_id' => $event->id,
                'type' => $event->type,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'already_processed']);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $transactionId = $session->metadata->transaction_id ?? null;

            if (! $transactionId) {
                return response()->json(['error' => 'Missing transaction id'], 400);
            }

            $transaction = WalletTransaction::find($transactionId);
            if (! $transaction) {
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            $this->ledger->creditDeposit($transaction, $event->id);
        }

        return response()->json(['status' => 'success']);
    }
}