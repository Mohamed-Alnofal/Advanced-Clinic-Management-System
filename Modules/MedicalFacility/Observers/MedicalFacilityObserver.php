<?php

namespace Modules\MedicalFacility\Observers;

use Illuminate\Support\Facades\Log;
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Models\User;

class MedicalFacilityObserver
{
    public function created(MedicalFacility $facility): void
    {
        /*
         * Notify admins only when the request
         * requires approval.
         */
        if ($facility->status !== 'pending') {
            return;
        }

        $this->notifyAdminsNewRequest($facility);
    }

    public function updated(MedicalFacility $facility): void
    {
        /*
         * We only care when status changes.
         */
        if (! $facility->wasChanged('status')) {
            return;
        }

        if ($facility->status === 'approved') {
            $this->notifyOwnerApproved($facility);
        }

        if ($facility->status === 'rejected') {
            $this->notifyOwnerRejected($facility);
        }
    }

    /**
     * Notify all admins about a new request.
     */
    private function notifyAdminsNewRequest(
        MedicalFacility $facility
    ): void {
        $admins = User::role('admin')->get();

        $type = $this->getFacilityType($facility);

        foreach ($admins as $admin) {
            app(NotificationService::class)->sendToUser(
                $admin,
                'New Medical Facility Request 🏥',
                "A new {$type} request requires your approval.",
                [
                    'medical_facility_id' => $facility->id,
                    'type' => $facility->type,
                    'status' => $facility->status,
                ]
            );
        }
    }

    /**
     * Notify owner after approval.
     */
    private function notifyOwnerApproved(
        MedicalFacility $facility
    ): void {
        $user = $this->getOwnerUser($facility);

        if (! $user) {
            Log::warning(
                'Medical facility owner not found for approval notification.',
                [
                    'medical_facility_id' => $facility->id,
                    'type' => $facility->type,
                ]
            );

            return;
        }

        $type = $this->getFacilityType($facility);

        app(NotificationService::class)->sendToUser(
            $user,
            'Request Approved ✅',
            "Your {$type} request has been approved by the administrator.",
            [
                'medical_facility_id' => $facility->id,
                'type' => $facility->type,
                'status' => $facility->status,
            ]
        );
    }

    /**
     * Notify owner after rejection.
     */
    private function notifyOwnerRejected(
        MedicalFacility $facility
    ): void {
        $user = $this->getOwnerUser($facility);

        if (! $user) {
            Log::warning(
                'Medical facility owner not found for rejection notification.',
                [
                    'medical_facility_id' => $facility->id,
                    'type' => $facility->type,
                ]
            );

            return;
        }

        $type = $this->getFacilityType($facility);

        $reason = $facility->reject_reason
            ?? 'No reason provided';

        app(NotificationService::class)->sendToUser(
            $user,
            'Request Rejected ❌',
            "Your {$type} request has been rejected. Reason: {$reason}",
            [
                'medical_facility_id' => $facility->id,
                'type' => $facility->type,
                'status' => $facility->status,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Get the owner user according to facility type.
     */
    private function getOwnerUser(MedicalFacility $facility)
    {
        $facility->loadMissing([
            'clinic.doctor.user',
            'pharmacy.pharmacist.user',
        ]);

        return match ($facility->type) {
            'clinic' => $facility->clinic?->doctor?->user,
            'pharmacy' => $facility->pharmacy?->pharmacist?->user,
            'lab' => $facility->lab?->user,
            default => null,
        };
    }

    /**
     * Get readable facility type.
     */
    private function getFacilityType(
        MedicalFacility $facility
    ): string {
        return match ($facility->type) {
            'clinic' => 'clinic',
            'pharmacy' => 'pharmacy',
            'lab' => 'laboratory',
            'hospital' => 'hospital',
            'ambulance' => 'ambulance',
            default => 'medical facility',
        };
    }
}