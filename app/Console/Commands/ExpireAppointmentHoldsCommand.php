<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Wallet\Models\WalletHold;
use Modules\Wallet\Services\WalletHoldService;

class ExpireAppointmentHoldsCommand extends Command
{
    protected $signature = 'appointments:expire-holds';

    protected $description = 'Release expired wallet holds and cancel ignored pending/accepted appointments';

    public function handle(WalletHoldService $holds): int
    {
        $expired = WalletHold::query()
            ->where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($expired as $hold) {
            DB::transaction(function () use ($hold, $holds, &$count) {
                $locked = WalletHold::whereKey($hold->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'held') {
                    return;
                }

                $holds->release($locked);

                $appointment = Appointment::whereKey($locked->appointment_id)->lockForUpdate()->first();
                if ($appointment && in_array($appointment->status, ['pending', 'accepted'], true)) {
                    $appointment->update([
                        'status' => 'cancelled',
                        'cancel_reason' => 'Payment hold expired',
                    ]);
                    if ($appointment->schedule_id) {
                        DoctorSchedule::where('id', $appointment->schedule_id)
                            ->update(['is_booked' => false]);
                    }
                }

                $count++;
            });
        }

        $this->info("Expired {$count} appointment hold(s).");

        return self::SUCCESS;
    }
}
