<?php

namespace Modules\Core\Services;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\MedicalVisits\Models\VitalSign;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Models\Patients;

/**
 * Builds a compact, privacy-aware patient context string for emergency triage prompts.
 * Only used when the caller is an authenticated patient.
 */
class EmergencyPatientContextService
{
    public function buildPromptBlock(?int $patientId): ?string
    {
        if ($patientId === null) {
            return null;
        }

        $bundle = $this->loadBundle($patientId);
        if ($bundle === null) {
            return null;
        }

        $lines = ['سياق طبي مختصر للمريض (استخدمه بحذر — لا تستبدل وصف الحالة الحالية):'];

        if ($bundle['vitals'] !== []) {
            $lines[] = 'آخر فيتالات: '.$this->formatVitals($bundle['vitals']);
        }

        if ($bundle['ocular'] !== []) {
            $lines[] = 'تاريخ عيني معروف: '.implode('؛ ', $bundle['ocular']);
        }

        if ($bundle['surgeries'] !== []) {
            $lines[] = 'عمليات سابقة: '.implode('؛ ', $bundle['surgeries']);
        }

        if ($bundle['medications'] !== []) {
            $lines[] = 'أدوية حديثة من الزيارات: '.implode('، ', array_slice($bundle['medications'], 0, 8));
        }

        if ($bundle['family'] !== []) {
            $lines[] = 'تاريخ عائلي (ليس أمراض المريض نفسه): '.implode('؛ ', $bundle['family']);
        }

        if (count($lines) === 1) {
            return null;
        }

        $lines[] = 'ملاحظة: هذا السياق مساعد فقط؛ اعتمد أولاً على وصف الحالة الطارئة الحالي.';

        return implode("\n", $lines);
    }

    /**
     * Structured EHR fragments for break-glass snapshots (correct semantic mapping).
     *
     * @return array{
     *   allergies: list<array<string, mixed>>,
     *   chronic: list<array<string, mixed>>,
     *   family_histories: list<array<string, mixed>>,
     *   vitals: list<array<string, mixed>>,
     *   visits: list<array<string, mixed>>
     * }|null
     */
    public function loadSnapshotData(?int $patientId): ?array
    {
        if ($patientId === null) {
            return null;
        }

        $patient = Patients::find($patientId);
        if (! $patient) {
            return null;
        }

        $patientFile = PatientFile::with(['familyHistories', 'ocularHistories', 'surgeries'])
            ->where('patient_id', $patient->id)
            ->first();

        $vitals = $patientFile
            ? VitalSign::where('patient_file_id', $patientFile->id)
                ->latest('id')
                ->limit(5)
                ->get()
                ->toArray()
            : [];

        $visits = $patientFile
            ? MedicalVisits::where('patient_file_id', $patientFile->id)
                ->with(['prescriptions.items'])
                ->latest('visit_date')
                ->limit(3)
                ->get()
                ->toArray()
            : [];

        $chronic = [];
        foreach ($patientFile?->ocularHistories ?? [] as $row) {
            $chronic[] = [
                'source' => 'ocular',
                'condition' => $row->condition ?? null,
            ];
        }
        foreach ($patientFile?->surgeries ?? [] as $row) {
            $chronic[] = [
                'source' => 'surgery',
                'name' => $row->surgery_name ?? null,
                'date' => $row->surgery_date?->format('Y-m-d'),
            ];
        }

        $family = [];
        foreach ($patientFile?->familyHistories ?? [] as $row) {
            $family[] = [
                'source' => 'family_history',
                'relative' => $row->relative ?? null,
                'disease' => $row->disease ?? null,
            ];
        }

        // No dedicated allergies table yet — keep empty rather than inventing data.
        return [
            'allergies' => [],
            'chronic' => $chronic,
            'family_histories' => $family,
            'vitals' => $vitals,
            'visits' => $visits,
        ];
    }

    /** @return array{vitals: list<array>, ocular: list<string>, surgeries: list<string>, medications: list<string>, family: list<string>}|null */
    private function loadBundle(int $patientId): ?array
    {
        $data = $this->loadSnapshotData($patientId);
        if ($data === null) {
            return null;
        }

        $ocular = [];
        foreach ($data['chronic'] as $row) {
            if (($row['source'] ?? '') === 'ocular' && ! empty($row['condition'])) {
                $ocular[] = (string) $row['condition'];
            }
        }

        $surgeries = [];
        foreach ($data['chronic'] as $row) {
            if (($row['source'] ?? '') === 'surgery' && ! empty($row['name'])) {
                $surgeries[] = (string) $row['name'];
            }
        }

        $family = [];
        foreach ($data['family_histories'] as $row) {
            $disease = trim((string) ($row['disease'] ?? ''));
            $relative = trim((string) ($row['relative'] ?? ''));
            if ($disease !== '') {
                $family[] = trim($relative !== '' ? "{$relative}: {$disease}" : $disease);
            }
        }

        $medications = [];
        foreach ($data['visits'] as $visit) {
            foreach ($visit['prescriptions'] ?? [] as $rx) {
                foreach ($rx['items'] ?? [] as $item) {
                    $name = trim((string) ($item['medicine_name'] ?? $item['name'] ?? $item['drug_name'] ?? ''));
                    if ($name !== '') {
                        $medications[] = $name;
                    }
                }
            }
        }

        return [
            'vitals' => $data['vitals'],
            'ocular' => $ocular,
            'surgeries' => $surgeries,
            'medications' => array_values(array_unique($medications)),
            'family' => $family,
        ];
    }

    /** @param list<array<string, mixed>> $vitals */
    private function formatVitals(array $vitals): string
    {
        $latest = $vitals[0] ?? null;
        if (! is_array($latest)) {
            return 'غير متوفر';
        }

        $parts = [];
        foreach (['blood_type', 'rh_factor', 'height', 'weight', 'bmi'] as $key) {
            if (isset($latest[$key]) && $latest[$key] !== null && $latest[$key] !== '') {
                $parts[] = "{$key}={$latest[$key]}";
            }
        }

        return $parts === [] ? 'مسجّلة بدون قيم مقروءة' : implode(', ', $parts);
    }
}
