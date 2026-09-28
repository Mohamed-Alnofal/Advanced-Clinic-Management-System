<?php

namespace Modules\Appointments\Services;

use Illuminate\Validation\ValidationException;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use Modules\Wallet\Models\WalletHold;
use Modules\Wallet\Services\WalletHoldService;
use Modules\Wallet\Services\WalletLedgerService;

/**
 * Wallet escrow for paid appointments.
 * Video visits always use wallet escrow (no cash / pay-at-clinic).
 * Clinic visits hold funds only when the patient opts into wallet escrow.
 */
class AppointmentEscrowService
{
    public function __construct(
        private WalletHoldService $holdService,
        private WalletLedgerService $ledger,
    ) {}

    public function maybeCreateHold(
        Appointment $appointment,
        User $user,
        DoctorSchedule $schedule,
        array $data,
    ): void {
        $fee = (float) ($schedule->price ?? 0);
        if ($fee <= 0) {
            return;
        }

        $isVideo = ($appointment->appointment_type === 'video');
        $wantsEscrow = $isVideo
            || filter_var($data['use_wallet_escrow'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $wantsEscrow) {
            $appointment->update([
                'consultation_fee' => $fee,
                'payment_method' => 'pay_at_clinic',
            ]);

            return;
        }

        $wallet = Wallet::where('user_id', $user->id)->first();
        if (! $wallet) {
            throw ValidationException::withMessages([
                'wallet' => 'Wallet not found. Top up your wallet before booking a paid appointment.',
            ]);
        }

        $hold = $this->holdService->createHold(
            $wallet,
            $fee,
            $appointment->id,
            'hold-appt-'.$appointment->id,
        );

        $appointment->update([
            'payment_hold_id' => $hold->id,
            'consultation_fee' => $fee,
            'payment_method' => 'wallet_escrow',
        ]);
    }

    public function releaseIfHeld(Appointment $appointment): void
    {
        $hold = $this->resolveHold($appointment);
        if (! $hold || $hold->status !== 'held') {
            return;
        }

        $this->holdService->release($hold);
    }

    public function captureIfHeld(Appointment $appointment): void
    {
        $this->captureAndPayDoctor($appointment);
    }

    /**
     * Finalize escrow when the appointment is completed: capture hold and credit doctor wallet.
     */
    public function captureAndPayDoctor(Appointment $appointment): void
    {
        $hold = $this->resolveHold($appointment);
        if (! $hold || $hold->status !== 'held') {
            return;
        }

        $this->holdService->capture($hold);
        $this->payoutToDoctor($appointment, (float) $hold->amount);
    }

    private function payoutToDoctor(Appointment $appointment, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $doctor = Doctor::find($appointment->doctor_id);
        if (! $doctor?->user_id) {
            return;
        }

        $doctorWallet = Wallet::firstOrCreate(
            ['user_id' => $doctor->user_id],
            ['balance' => 0],
        );

        $this->ledger->credit(
            $doctorWallet,
            $amount,
            'payout',
            'appt-payout-'.$appointment->id,
        );
    }

    private function resolveHold(Appointment $appointment): ?WalletHold
    {
        if (! $appointment->payment_hold_id) {
            return null;
        }

        return WalletHold::find($appointment->payment_hold_id);
    }
}
