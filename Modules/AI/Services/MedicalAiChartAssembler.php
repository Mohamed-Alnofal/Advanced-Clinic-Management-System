<?php

namespace Modules\AI\Services;

use Modules\MedicalVisits\Models\FamilyHistory;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\MedicalVisits\Models\Surgery;
use Modules\MedicalVisits\Models\VitalSign;
use Modules\MedicalVisits\Services\MedicalVisitService;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Models\Patients;
use Modules\Patients\Services\PatientService;
use Modules\Users\Models\Relatives;
use Modules\Users\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Builds a compact, privacy-aware chart snapshot for the medical AI.
 * Patients see their own visits. Doctors only see visits already visible to them.
 */
class MedicalAiChartAssembler
{
    public function __construct(
        private PatientService $patients,
        private MedicalVisitService $visits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAuthenticatedPatient(User $user): array
    {
        $patient = $this->patients->createForUser($user);
        $user->setRelation('patient', $patient);

        return $this->assemble($patient, $user, isOwner: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function forDoctorLookup(User $doctorUser, int $lookupId, string $source): array
    {
        if (! $doctorUser->doctor) {
            throw new AccessDeniedHttpException('Only doctors can open a clinical briefing.');
        }

        $source = strtolower(trim($source));
        $model = match ($source) {
            'relative' => Relatives::query()->findOrFail($lookupId),
            default => User::query()->findOrFail($lookupId),
        };

        $created = $this->patients->create($model, (int) $doctorUser->doctor->id);
        /** @var Patients $patient */
        $patient = $created['patient'];

        return $this->assemble($patient, $doctorUser, isOwner: false, displayName: (string) ($model->name ?? ''));
    }

    /**
     * Compact text for the LLM (never dump raw encrypted payloads).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function promptBlock(array $snapshot, int $maxVisits = 8): string
    {
        if (($snapshot['visit_count'] ?? 0) === 0 && empty($snapshot['medications']) && empty($snapshot['surgeries'])) {
            return 'Patient chart: no clinical visits recorded yet.';
        }

        $lines = ['Patient chart (use as background only; do not invent missing facts):'];
        $profile = $snapshot['patient'] ?? [];
        $bits = [];
        foreach (['name', 'age', 'gender', 'blood_type', 'bmi'] as $key) {
            if (! empty($profile[$key])) {
                $bits[] = $key.': '.$profile[$key];
            }
        }
        if ($bits !== []) {
            $lines[] = implode(' | ', $bits);
        }

        $visits = array_slice((array) ($snapshot['visits'] ?? []), 0, $maxVisits);
        foreach ($visits as $visit) {
            $lines[] = sprintf(
                '- Visit %s | %s | %s | complaint: %s | diagnosis: %s | meds: %s',
                $visit['visit_date'] ?? 'n/a',
                $visit['doctor_name'] ?? 'doctor',
                $visit['category'] ?? 'general',
                $this->clip((string) ($visit['chief_complaint'] ?? ''), 120) ?: '—',
                $this->clip((string) ($visit['diagnosis'] ?? ''), 120) ?: '—',
                $visit['medications'] !== [] ? implode(', ', array_slice($visit['medications'], 0, 4)) : 'none listed'
            );
        }

        $surgeries = (array) ($snapshot['surgeries'] ?? []);
        if ($surgeries !== []) {
            $lines[] = 'Surgeries: '.implode(', ', array_map(
                fn ($row) => trim(($row['surgery_name'] ?? '').' '.($row['surgery_date'] ?? '')),
                $surgeries
            ));
        }

        $family = (array) ($snapshot['family_history'] ?? []);
        if ($family !== []) {
            $lines[] = 'Family history: '.implode(', ', array_map(
                fn ($row) => trim(($row['relative'] ?? '').' — '.($row['disease'] ?? '')),
                $family
            ));
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(Patients $patient, User $viewer, bool $isOwner, ?string $displayName = null): array
    {
        $file = PatientFile::query()->where('patient_id', $patient->id)->first();
        $owner = $patient->patientable;
        $name = $displayName ?: (string) ($owner->name ?? '');

        $profile = [
            'name' => $name !== '' ? $name : null,
            'age' => $owner->age ?? null,
            'gender' => $owner->gender ?? null,
            'blood_type' => null,
            'rh_factor' => null,
            'height' => null,
            'weight' => null,
            'bmi' => null,
        ];

        $visitsOut = [];
        $medications = [];
        $surgeries = [];
        $family = [];

        if ($file) {
            $vital = VitalSign::query()->where('patient_file_id', $file->id)->latest('id')->first();
            if ($vital) {
                $profile['blood_type'] = $vital->blood_type ?: null;
                $profile['rh_factor'] = $vital->rh_factor ?: null;
                $profile['height'] = $vital->height;
                $profile['weight'] = $vital->weight;
                $profile['bmi'] = $vital->bmi;
            }

            try {
                $query = MedicalVisits::query()
                    ->where('patient_file_id', $file->id)
                    ->with([
                        'doctor.user:id,name',
                        'doctor.specialization',
                        'prescriptions.items',
                    ])
                    ->latest('visit_date')
                    ->latest('id');

                if (! $isOwner && $viewer->doctor) {
                    $this->visits->scopeVisibleToDoctor($query, (int) $viewer->doctor->id);
                } elseif (! $isOwner) {
                    throw new AccessDeniedHttpException('Unauthorized.');
                }

                $visits = $query->limit(12)->get();
            } catch (AccessDeniedHttpException $e) {
                throw $e;
            } catch (Throwable) {
                $visits = collect();
            }

            foreach ($visits as $visit) {
                $meds = [];
                foreach ($visit->prescriptions as $rx) {
                    foreach ($rx->items as $item) {
                        $label = trim((string) $item->medicine_name);
                        if ($label !== '') {
                            $meds[] = $label;
                            $medications[] = [
                                'name' => $label,
                                'dose' => $item->dose,
                                'frequency' => $item->frequency,
                                'duration' => $item->duration,
                                'visit_date' => optional($visit->visit_date)?->toDateString(),
                            ];
                        }
                    }
                }

                $visitsOut[] = [
                    'id' => (int) $visit->id,
                    'visit_date' => optional($visit->visit_date)?->toDateString(),
                    'category' => $visit->category,
                    'doctor_name' => $visit->doctor?->user?->name,
                    'specialty' => $visit->doctor?->specialization?->name,
                    'chief_complaint' => $this->clip((string) $visit->chief_complaint, 220),
                    'diagnosis' => $this->clip((string) $visit->diagnosis, 220),
                    'notes' => $this->clip((string) ($visit->notes ?? ''), 180),
                    'medications' => array_values(array_unique($meds)),
                ];
            }

            try {
                $surgeries = Surgery::query()
                    ->where('patient_file_id', $file->id)
                    ->latest('surgery_date')
                    ->limit(8)
                    ->get()
                    ->map(fn (Surgery $row) => [
                        'surgery_name' => $row->surgery_name,
                        'surgery_date' => optional($row->surgery_date)?->toDateString(),
                        'notes' => $this->clip((string) ($row->notes ?? ''), 120),
                    ])
                    ->all();
            } catch (Throwable) {
                $surgeries = [];
            }

            try {
                $family = FamilyHistory::query()
                    ->where('patient_file_id', $file->id)
                    ->limit(8)
                    ->get()
                    ->map(fn (FamilyHistory $row) => [
                        'relative' => $row->relative,
                        'disease' => $row->disease,
                    ])
                    ->all();
            } catch (Throwable) {
                $family = [];
            }
        }

        $uniqueMeds = [];
        foreach ($medications as $med) {
            $key = mb_strtolower((string) $med['name']);
            if (! isset($uniqueMeds[$key])) {
                $uniqueMeds[$key] = $med;
            }
        }

        return [
            'patient' => $profile,
            'visit_count' => count($visitsOut),
            'visits' => $visitsOut,
            'medications' => array_values($uniqueMeds),
            'surgeries' => $surgeries,
            'family_history' => $family,
            'has_chart' => count($visitsOut) > 0 || $surgeries !== [] || $family !== [],
        ];
    }

    private function clip(string $text, int $max): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
