<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Ambulance\Models\EmergencyRequestOffer;
use Modules\Ambulance\Services\AmbulanceDispatchService;
use Modules\Ambulance\Services\AmbulancePatientNotifier;
use Modules\Ambulance\Services\EmergencyRequestService;

/**
 * Professional SOS lifecycle hygiene:
 * - Escalate unanswered pending requests (flag for company/admin attention)
 * - Auto-cancel very stale pending requests so patients are not locked forever
 * - Auto-cancel abandoned assigned missions and free the unit
 *
 * Uses atomic updates so concurrent schedule:work processes cannot double-notify.
 */
class ExpireStaleEmergencyRequestsCommand extends Command
{
    protected $signature = 'ambulance:expire-stale-requests
        {--escalate-minutes= : Mark pending requests as escalated after this many minutes}
        {--pending-minutes= : Auto-cancel unanswered pending requests after this many minutes}
        {--assigned-hours= : Auto-cancel stuck assigned/arrived missions after this many hours}';

    protected $description = 'Escalate and expire stale emergency SOS requests';

    public function handle(AmbulanceDispatchService $dispatch, AmbulancePatientNotifier $notifier): int
    {
        $escalateMinutes = max(1, (int) ($this->option('escalate-minutes')
            ?: EmergencyRequestService::escalateAfterMinutes()));
        $pendingMinutes = max($escalateMinutes, (int) ($this->option('pending-minutes')
            ?: EmergencyRequestService::pendingExpireMinutes()));
        $assignedHours = max(1, (int) ($this->option('assigned-hours')
            ?: EmergencyRequestService::assignedExpireHours()));

        $escalated = 0;
        $cancelledPending = 0;
        $cancelledAssigned = 0;

        // 1) Escalate — claim row only if still unescalated
        EmergencyRequest::query()
            ->where('status', 'pending')
            ->whereNull('escalated_at')
            ->where('requested_at', '<', now()->subMinutes($escalateMinutes))
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->each(function ($id) use (&$escalated, $notifier) {
                $claimed = EmergencyRequest::query()
                    ->whereKey($id)
                    ->where('status', 'pending')
                    ->whereNull('escalated_at')
                    ->update(['escalated_at' => now()]);

                if ($claimed !== 1) {
                    return;
                }

                $request = EmergencyRequest::find($id);
                if ($request) {
                    $notifier->notify($request, 'escalated');
                    $escalated++;
                }
            });

        // 2) Auto-cancel stale pending
        EmergencyRequest::query()
            ->where('status', 'pending')
            ->where('requested_at', '<', now()->subMinutes($pendingMinutes))
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->each(function ($id) use (&$cancelledPending, $notifier) {
                $request = DB::transaction(function () use ($id) {
                    $locked = EmergencyRequest::whereKey($id)
                        ->where('status', 'pending')
                        ->lockForUpdate()
                        ->first();

                    if (! $locked) {
                        return null;
                    }

                    EmergencyRequestOffer::query()
                        ->where('emergency_request_id', $locked->id)
                        ->where('status', 'offered')
                        ->update([
                            'status' => 'expired',
                            'responded_at' => now(),
                        ]);

                    $locked->update([
                        'status' => 'cancelled',
                        'cancel_reason' => 'auto_expired_no_driver_response',
                        'escalated_at' => $locked->escalated_at ?? now(),
                    ]);

                    return $locked->fresh();
                });

                if ($request) {
                    $notifier->notify($request, 'auto_expired');
                    $cancelledPending++;
                }
            });

        // 3) Auto-cancel abandoned assigned/arrived missions
        EmergencyRequest::query()
            ->whereIn('status', ['assigned', 'arrived'])
            ->where(function ($q) use ($assignedHours) {
                $q->where('accepted_at', '<', now()->subHours($assignedHours))
                    ->orWhere(function ($q2) use ($assignedHours) {
                        $q2->whereNull('accepted_at')
                            ->where('requested_at', '<', now()->subHours($assignedHours));
                    });
            })
            ->orderBy('id')
            ->limit(50)
            ->pluck('id')
            ->each(function ($id) use (&$cancelledAssigned, $notifier) {
                $request = DB::transaction(function () use ($id) {
                    $locked = EmergencyRequest::whereKey($id)
                        ->whereIn('status', ['assigned', 'arrived'])
                        ->lockForUpdate()
                        ->first();

                    if (! $locked) {
                        return null;
                    }

                    if ($locked->ambulance_unit_id) {
                        $unit = AmbulanceUnit::find($locked->ambulance_unit_id);
                        if ($unit) {
                            $unit->update(['status' => 'available']);
                        }
                    }

                    $locked->update([
                        'status' => 'cancelled',
                        'cancel_reason' => 'auto_expired_stale_mission',
                        'escalated_at' => $locked->escalated_at ?? now(),
                    ]);

                    return $locked->fresh();
                });

                if ($request) {
                    $notifier->notify($request, 'auto_expired');
                    $cancelledAssigned++;
                }
            });

        // Re-offer escalated-but-still-pending that have no live offer
        EmergencyRequest::query()
            ->where('status', 'pending')
            ->whereNotNull('escalated_at')
            ->whereDoesntHave('offers', fn ($q) => $q->where('status', 'offered'))
            ->orderBy('id')
            ->limit(30)
            ->each(function (EmergencyRequest $request) use ($dispatch) {
                $dispatch->offerToNearest($request);
            });

        $this->info("Escalated {$escalated}, cancelled pending {$cancelledPending}, cancelled assigned {$cancelledAssigned} (escalate={$escalateMinutes}m, pending={$pendingMinutes}m).");

        return self::SUCCESS;
    }
}
