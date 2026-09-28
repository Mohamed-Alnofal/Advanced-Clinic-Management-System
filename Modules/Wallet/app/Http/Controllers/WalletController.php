<?php

namespace Modules\Wallet\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Services\WalletLedgerService;
use Modules\Wallet\Services\WalletTopUpService;
use Modules\WalletTransaction\Models\WalletTransaction;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class WalletController
{
    public function __construct(
        private WalletTopUpService $service,
        private WalletLedgerService $ledger,
    ) {}

    public function create(Request $request)
    {
        $wallet = $this->service->createWallet($request->user());

        return response()->json([
            'message' => 'Wallet created successfully',
            'wallet' => $wallet,
        ]);
    }

    public function topUp(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $result = $this->service->createCheckout(
            $request->user(),
            (float) $request->amount
        );

        if (! empty($result['instant'])) {
            return response()->json([
                'message' => 'Wallet topped up successfully',
                'data' => $result,
            ]);
        }

        return response()->json([
            'message' => 'Checkout created successfully',
            'data' => $result,
        ]);
    }

    public function stripeSuccess(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            $session = Session::retrieve($request->session_id);

            if ($session->payment_status === 'paid') {
                $transaction = WalletTransaction::find($session->metadata->transaction_id ?? null);
                if ($transaction) {
                    $this->ledger->creditDeposit($transaction);
                }
            }
        } catch (\Throwable) {
            // Still show a success page so the mobile browser is not a Laravel 500.
        }

        return response(
            '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:48px">'
            .'<h1>Payment received</h1>'
            .'<p>You can close this page and return to the app. Your wallet will refresh automatically.</p>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public function handle(Request $request)
    {
        return $this->service->handle($request);
    }

    public function balance()
    {
        return $this->getBalance();
    }

    public function transactions()
    {
        return $this->getTransactions();
    }

    public function getBalance()
    {
        $wallet = Wallet::where('user_id', auth()->id())->first();

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        try {
            $this->service->syncPendingDeposits($wallet);
        } catch (\Throwable) {
            // Never fail balance reads after Stripe return.
        }

        return response()->json(['balance' => $wallet->fresh()->balance]);
    }

    public function getTransactions()
    {
        $wallet = Wallet::where('user_id', auth()->id())->first();

        if (! $wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        try {
            $this->service->syncPendingDeposits($wallet);
        } catch (\Throwable) {
            // Never fail transaction reads after Stripe return.
        }
        $wallet->refresh();
        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'balance' => $wallet->balance,
            'transactions' => $transactions,
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $wallet = Wallet::where('user_id', auth()->id())->firstOrFail();
            $this->ledger->debit($wallet, (float) $request->amount, 'withdraw');

            return response()->json([
                'message' => 'Withdrawal successful',
                'balance' => $wallet->fresh()->balance,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
