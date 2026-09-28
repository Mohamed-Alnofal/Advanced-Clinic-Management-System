<?php

namespace Modules\Wallet\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Models\WalletTransaction;

class WalletLedgerService
{
    /**
     * Idempotent credit — safe for Stripe success URL + webhook race.
     */
    public function creditDeposit(WalletTransaction $transaction, ?string $stripeEventId = null): Wallet
    {
        return DB::transaction(function () use ($transaction, $stripeEventId) {
            $tx = WalletTransaction::whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tx->status === 'success') {
                return $tx->wallet()->first() ?? Wallet::whereKey($tx->wallet_id)->firstOrFail();
            }

            $wallet = Wallet::whereKey($tx->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->increment('balance', $tx->amount);

            $payload = ['status' => 'success'];
            if ($stripeEventId && \Illuminate\Support\Facades\Schema::hasColumn('wallet_transactions', 'stripe_event_id')) {
                $payload['stripe_event_id'] = $stripeEventId;
            }
            $tx->update($payload);

            return $wallet->fresh();
        });
    }

    public function debit(Wallet $wallet, float $amount, string $type = 'withdraw', ?string $idempotencyKey = null): WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $amount, $type, $idempotencyKey) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($locked->balance < $amount) {
                throw new \RuntimeException('Insufficient balance');
            }

            $locked->decrement('balance', $amount);

            return WalletTransaction::create([
                'wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'status' => 'success',
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Credit a wallet (payouts, refunds to provider, etc.) with optional idempotency.
     */
    public function credit(
        Wallet $wallet,
        float $amount,
        string $type = 'deposit',
        ?string $idempotencyKey = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $locked->increment('balance', $amount);

            return WalletTransaction::create([
                'wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'status' => 'success',
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }
}
