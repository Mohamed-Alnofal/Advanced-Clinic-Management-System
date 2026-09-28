<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Services\WalletLedgerService;
use Modules\Wallet\Services\WalletTopUpService;
use Modules\WalletTransaction\Models\WalletTransaction;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    private function topUpService(): WalletTopUpService
    {
        return app(WalletTopUpService::class);
    }

    private function ledger(): WalletLedgerService
    {
        return app(WalletLedgerService::class);
    }

    public function test_create_wallet_initializes_zero_balance(): void
    {
        $user = $this->createPatient();

        $wallet = $this->topUpService()->createWallet($user);

        $this->assertSame($user->id, $wallet->user_id);
        $this->assertEquals(0, (float) $wallet->balance);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 0,
        ]);
    }

    public function test_create_wallet_is_idempotent_for_same_user(): void
    {
        $user = $this->createPatient();
        $service = $this->topUpService();

        $first = $service->createWallet($user);
        $first->forceFill(['balance' => 75])->save();

        $second = $service->createWallet($user);

        $this->assertSame($first->id, $second->id);
        $this->assertEquals(75, (float) $second->fresh()->balance);
    }

    public function test_increment_and_decrement_balance_with_decimal_precision(): void
    {
        $user = $this->createPatient();
        $wallet = $this->createWalletForUser($user, 100.00);

        $wallet->increment('balance', 25.55);
        $wallet->refresh();
        $this->assertEqualsWithDelta(125.55, (float) $wallet->balance, 0.001);

        $wallet->decrement('balance', 25.55);
        $wallet->refresh();
        $this->assertEqualsWithDelta(100.00, (float) $wallet->balance, 0.001);
    }

    public function test_successful_deposit_transaction_updates_balance_atomically(): void
    {
        $user = $this->createPatient();
        $wallet = $this->createWalletForUser($user, 50.00);

        DB::transaction(function () use ($wallet): void {
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount' => 30.25,
                'type' => 'deposit',
                'status' => 'pending',
            ]);

            $this->ledger()->creditDeposit($transaction);
        });

        $wallet->refresh();
        $this->assertEqualsWithDelta(80.25, (float) $wallet->balance, 0.001);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'amount' => 30.25,
            'type' => 'deposit',
            'status' => 'success',
        ]);
    }

    public function test_withdraw_prevents_negative_balance(): void
    {
        $user = $this->createPatient();
        $wallet = $this->createWalletForUser($user, 20.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $this->ledger()->debit($wallet, 50.00);
    }

    public function test_pending_transaction_does_not_change_balance_until_committed(): void
    {
        $user = $this->createPatient();
        $wallet = $this->createWalletForUser($user, 40.00);

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => 15.00,
            'type' => 'deposit',
            'status' => 'pending',
        ]);

        $this->assertEqualsWithDelta(40.00, (float) $wallet->fresh()->balance, 0.001);
    }

    public function test_credit_deposit_is_idempotent_under_repeated_calls(): void
    {
        $user = $this->createPatient();
        $wallet = $this->createWalletForUser($user, 10.00);

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => 40.00,
            'type' => 'deposit',
            'status' => 'pending',
        ]);

        $this->ledger()->creditDeposit($transaction);
        $this->ledger()->creditDeposit($transaction->fresh());
        $this->ledger()->creditDeposit($transaction->fresh());

        $this->assertEqualsWithDelta(50.00, (float) $wallet->fresh()->balance, 0.001);
        $this->assertSame(1, WalletTransaction::where('wallet_id', $wallet->id)
            ->where('status', 'success')
            ->where('type', 'deposit')
            ->count());
    }

    public function test_lock_for_update_serializes_sequential_debits(): void
    {
        $user = $this->createPatient();
        $wallet = $this->createWalletForUser($user, 100.00);
        $ledger = $this->ledger();

        $ledger->debit($wallet, 30.00, 'withdraw', 'debit-1');
        $ledger->debit($wallet->fresh(), 25.00, 'withdraw', 'debit-2');

        $this->assertEqualsWithDelta(45.00, (float) $wallet->fresh()->balance, 0.001);

        $duplicate = $ledger->debit($wallet->fresh(), 30.00, 'withdraw', 'debit-1');
        $this->assertEqualsWithDelta(30.00, (float) $duplicate->amount, 0.001);
        $this->assertEqualsWithDelta(45.00, (float) $wallet->fresh()->balance, 0.001);
    }
}
