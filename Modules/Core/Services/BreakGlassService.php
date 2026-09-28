<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\BreakGlassAccessLog;
use Modules\Core\Models\EmergencyMedicalSnapshot;
use Modules\Core\Models\EmergencySosSignal;
use Modules\Patients\Models\Patients;

class BreakGlassService
{
    public function __construct(
        private EmergencyPatientContextService $patientContext,
    ) {}

    public function shouldActivate(EmergencySosSignal $signal): bool
    {
        return $signal->patient_id !== null
            && in_array($signal->severity_level, [
                EmergencySosSignal::SEVERITY_CRITICAL,
                EmergencySosSignal::SEVERITY_URGENT,
            ], true);
    }

    public function createSnapshot(EmergencySosSignal $signal, ?string $ip = null): ?EmergencyMedicalSnapshot
    {
        if (! $this->shouldActivate($signal)) {
            return null;
        }

        $patient = Patients::find($signal->patient_id);
        if (! $patient) {
            return null;
        }

        return DB::transaction(function () use ($signal, $patient, $ip) {
            $bundle = $this->patientContext->loadSnapshotData($patient->id) ?? [
                'allergies' => [],
                'chronic' => [],
                'family_histories' => [],
                'vitals' => [],
                'visits' => [],
            ];

            $snapshot = EmergencyMedicalSnapshot::create([
                'sos_signal_id' => $signal->id,
                'patient_id' => $patient->id,
                'vitals_json' => $bundle['vitals'],
                // Real allergies source not modeled yet — never invent / never dump family history here.
                'allergies_json' => $bundle['allergies'],
                // Patient's own conditions (ocular/surgeries), NOT family history.
                'chronic_conditions_json' => $bundle['chronic'],
                'last_visits_json' => [
                    'visits' => $bundle['visits'],
                    'family_histories' => $bundle['family_histories'],
                ],
                'created_at' => now(),
            ]);

            BreakGlassAccessLog::create([
                'accessor_id' => 0,
                'accessor_type' => 'system',
                'patient_id' => $patient->id,
                'sos_signal_id' => $signal->id,
                'reason' => 'Emergency SOS triage — automatic break-the-glass medical snapshot',
                'ip' => $ip,
                'created_at' => now(),
            ]);

            return $snapshot;
        });
    }

    /**
     * Authorized reveal of an emergency medical snapshot with real accessor audit.
     */
    public function revealForUser(
        int $sosSignalId,
        $user,
        string $reason,
        ?string $ip = null,
    ): EmergencyMedicalSnapshot {
        $snapshot = EmergencyMedicalSnapshot::where('sos_signal_id', $sosSignalId)->latest('id')->firstOrFail();
        $signal = EmergencySosSignal::findOrFail($sosSignalId);

        if (! $this->userMayReveal($user, $signal)) {
            abort(403, 'Not authorized to access this emergency medical snapshot.');
        }

        BreakGlassAccessLog::create([
            'accessor_id' => (int) ($user->id ?? 0),
            'accessor_type' => $this->resolveAccessorType($user),
            'patient_id' => $snapshot->patient_id,
            'sos_signal_id' => $sosSignalId,
            'reason' => $reason,
            'ip' => $ip,
            'created_at' => now(),
        ]);

        return $snapshot;
    }

    private function userMayReveal($user, EmergencySosSignal $signal): bool
    {
        if (! $user) {
            return false;
        }

        if ($signal->user_id && (int) $signal->user_id === (int) $user->id) {
            return true;
        }

        if ($user->patient && (int) $user->patient->id === (int) $signal->patient_id) {
            return true;
        }

        if ($user->doctor) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('ambulance')) {
            return \Modules\Ambulance\Models\EmergencyRequest::query()
                ->where('sos_signal_id', $signal->id)
                ->where('ambulance_company_id', $user->ambulanceCompany?->id)
                ->exists();
        }

        return false;
    }

    private function resolveAccessorType($user): string
    {
        if ($user->doctor) {
            return 'doctor';
        }
        if ($user->patient) {
            return 'patient';
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('ambulance')) {
            return 'ambulance_company';
        }

        return 'user';
    }
}
