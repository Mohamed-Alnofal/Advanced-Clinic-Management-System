<?php

namespace Modules\Wallet\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHold;
use Modules\WalletTransaction\Models\WalletTransaction;

class WalletHoldService
{
    public function __construct(
        private WalletLedgerService $ledger,
    ) {}

    public function createHold(Wallet $wallet, float $amount, int $appointmentId, ?string $idempotencyKey = null): WalletHold
    {
        return DB::transaction(function () use ($wallet, $amount, $appointmentId, $idempotencyKey) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey) {
                $existing = WalletHold::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($locked->balance < $amount) {
                throw new \RuntimeException('Insufficient balance for consultation hold');
            }

            $locked->decrement('balance', $amount);

            $transaction = WalletTransaction::create([
                'wallet_id' => $locked->id,
                'type' => 'hold',
                'amount' => $amount,
                'status' => 'success',
                'idempotency_key' => $idempotencyKey,
            ]);

            return WalletHold::create([
                'wallet_id' => $locked->id,
                'appointment_id' => $appointmentId,
                'wallet_transaction_id' => $transaction->id,
                'amount' => $amount,
                'status' => 'held',
                'idempotency_key' => $idempotencyKey ?? 'hold-'.$appointmentId.'-'.now()->timestamp,
                'expires_at' => now()->addHours(24),
            ]);
        });
    }

    public function release(WalletHold $hold): WalletHold
    {
        return DB::transaction(function () use ($hold) {
            $locked = WalletHold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'held') {
                return $locked;
            }

            $wallet = Wallet::whereKey($locked->wallet_id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance', $locked->amount);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'refund',
                'amount' => $locked->amount,
                'status' => 'success',
            ]);

            $locked->update(['status' => 'released', 'released_at' => now()]);

            return $locked->fresh();
        });
    }

    public function capture(WalletHold $hold): WalletHold
    {
        return DB::transaction(function () use ($hold) {
            $locked = WalletHold::whereKey($hold->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'held') {
                return $locked;
            }

            WalletTransaction::create([
                'wallet_id' => $locked->wallet_id,
                'type' => 'payment',
                'amount' => $locked->amount,
                'status' => 'success',
            ]);

            $locked->update(['status' => 'captured', 'captured_at' => now()]);

            return $locked->fresh();
        });
    }
}
