<?php

namespace Tests\Feature\Api;

use Modules\Wallet\Models\WalletHold;
use Modules\Wallet\Services\WalletHoldService;
use Modules\Wallet\Services\WalletLedgerService;
use Modules\WalletTransaction\Models\WalletTransaction;
use Tests\TestCase;

/**
 * Pillar 2 — Financial wallet, idempotency, escrow holds.
 */
class WalletFinancialIntegrityApiTest extends TestCase
{
    public function test_stripe_deposit_credit_is_idempotent_when_applied_twice(): void
    {
        $patient = $this->createPatient();
        $wallet = $this->createWalletForUser($patient, 0);

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => 75.00,
            'type' => 'deposit',
            'status' => 'pending',
            'stripe_session_id' => 'cs_test_idempotent_001',
        ]);

        $ledger = app(WalletLedgerService::class);

        // Simulate Stripe success URL + webhook race (same payload twice).
        $ledger->creditDeposit($transaction);
        $ledger->creditDeposit($transaction->fresh());

        $this->assertEqualsWithDelta(75.00, (float) $wallet->fresh()->balance, 0.001);
        $this->assertSame('success', $transaction->fresh()->status);
        $this->assertSame(
            1,
            WalletTransaction::where('wallet_id', $wallet->id)
                ->where('type', 'deposit')
                ->where('status', 'success')
                ->count()
        );
    }

    public function test_booking_with_wallet_escrow_creates_hold_for_exact_fee(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => ['price' => 85.50, 'consultation_type' => 'clinic'],
        ]);
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 200.00);

        $response = $this->authenticateAs($patient)->postJson('/api/appointments/book', [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $setup['schedule']->id,
            'booking_for' => 'self',
            'appointment_type' => 'clinic',
            'use_wallet_escrow' => true,
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $appointmentId = (int) $response->json('data.appointment_id');

        $this->assertDatabaseHas('wallet_holds', [
            'appointment_id' => $appointmentId,
            'amount' => 85.50,
            'status' => 'held',
        ]);

        $this->assertEqualsWithDelta(114.50, (float) $patient->fresh()->wallet?->balance
            ?? \Modules\Wallet\Models\Wallet::where('user_id', $patient->id)->value('balance'), 0.001);

        $hold = WalletHold::where('appointment_id', $appointmentId)->first();
        $this->assertNotNull($hold);
        $this->assertEqualsWithDelta(85.50, (float) $hold->amount, 0.001);
    }

    public function test_escrow_hold_is_idempotent_for_same_appointment_key(): void
    {
        $patient = $this->createPatient();
        $wallet = $this->createWalletForUser($patient, 100.00);
        $setup = $this->createDoctorWithClinicAndSchedule();
        $appointment = $this->createAppointmentForPatient($patient, $setup);

        $holds = app(WalletHoldService::class);

        $first = $holds->createHold($wallet, 40.00, $appointment->id, 'hold-appt-'.$appointment->id);
        $second = $holds->createHold($wallet->fresh(), 40.00, $appointment->id, 'hold-appt-'.$appointment->id);

        $this->assertSame($first->id, $second->id);
        $this->assertEqualsWithDelta(60.00, (float) $wallet->fresh()->balance, 0.001);
        $this->assertSame(1, WalletHold::where('appointment_id', $appointment->id)->count());
    }

    public function test_escrow_booking_fails_when_wallet_balance_is_insufficient(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => ['price' => 150, 'consultation_type' => 'clinic'],
        ]);
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 20.00);

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'clinic_id' => $setup['clinic']->id,
                'schedule_id' => $setup['schedule']->id,
                'appointment_type' => 'clinic',
                'use_wallet_escrow' => true,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('wallet_holds', 0);
        $this->assertEqualsWithDelta(20.00, (float) \Modules\Wallet\Models\Wallet::where('user_id', $patient->id)->value('balance'), 0.001);
    }

    public function test_video_booking_uses_wallet_escrow_even_without_client_flag(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => ['price' => 70, 'consultation_type' => 'video'],
        ]);
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 200.00);

        $response = $this->authenticateAs($patient)->postJson('/api/appointments/book', [
            'doctor_id' => $setup['doctor']->id,
            'schedule_id' => $setup['schedule']->id,
            'booking_for' => 'self',
            'appointment_type' => 'video',
            'use_wallet_escrow' => false,
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $appointmentId = (int) $response->json('data.appointment_id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'payment_method' => 'wallet_escrow',
        ]);
        $this->assertDatabaseHas('wallet_holds', [
            'appointment_id' => $appointmentId,
            'amount' => 70,
            'status' => 'held',
        ]);
    }

    public function test_video_booking_fails_without_wallet_balance(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => ['price' => 80, 'consultation_type' => 'video'],
        ]);
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'schedule_id' => $setup['schedule']->id,
                'appointment_type' => 'video',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('wallet_holds', 0);
    }

    public function test_debit_with_idempotency_key_does_not_double_charge(): void
    {
        $patient = $this->createPatient();
        $wallet = $this->createWalletForUser($patient, 90.00);
        $ledger = app(WalletLedgerService::class);

        $ledger->debit($wallet, 15.00, 'withdraw', 'withdraw-once');
        $ledger->debit($wallet->fresh(), 15.00, 'withdraw', 'withdraw-once');

        $this->assertEqualsWithDelta(75.00, (float) $wallet->fresh()->balance, 0.001);
        $this->assertSame(1, WalletTransaction::where('idempotency_key', 'withdraw-once')->count());
    }
}
