<?php

namespace Modules\AI\Services;

use Modules\Users\Models\User;
use Throwable;

/**
 * Doctor-facing chart briefing. Educational copilot — not a diagnosis engine.
 */
class MedicalAiClinicalBriefingService
{
    public function __construct(
        private MedicalAiChartAssembler $charts,
        private UnifiedMedicalAiService $ai,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function brief(User $doctorUser, int $lookupId, string $source): array
    {
        $doctorUser->loadMissing(['doctor.specialization']);
        $snapshot = $this->charts->forDoctorLookup($doctorUser, $lookupId, $source);
        $specialty = (string) ($doctorUser->doctor?->specialization?->name ?? '');

        if (! ($snapshot['has_chart'] ?? false)) {
            return [
                'briefing_title' => 'Empty chart — first encounter',
                'clinical_summary' => implode("\n", [
                    '**No prior visits are visible for this patient yet.**',
                    '',
                    'Use the first visit to capture a structured history, vitals, current medicines, and allergies. This briefing will become richer after you save a visit.',
                ]),
                'risk_flags' => [],
                'visit_highlights' => [],
                'medication_watch' => [],
                'suggested_focus' => [
                    'Confirm identity, age, and presenting complaint.',
                    'Record vitals and chronic conditions before prescribing.',
                    'Ask about current medicines, surgeries, and family history.',
                ],
                'empty_chart' => true,
                'chart' => $snapshot,
                'ai_provider' => 'none',
                'used_fallback' => false,
            ];
        }

        $system = <<<'PROMPT'
You are a clinical copilot for a licensed physician inside a hospital system.
Summarize the patient chart to save the doctor time. Return ONLY valid JSON:

{
  "briefing_title": "short title",
  "clinical_summary": "markdown, 2-5 short sections: trajectory, what matters today, gaps",
  "risk_flags": ["concise flags only if evidence exists in the chart"],
  "visit_highlights": [{"date": "YYYY-MM-DD or unknown", "diagnosis": "string", "note": "one line"}],
  "medication_watch": ["named medicines to keep in mind — no new prescriptions"],
  "suggested_focus": ["3-6 exam or history questions for this encounter"]
}

RULES:
- Never invent labs, imaging, allergies, or diagnoses that are not in the chart.
- Do not write a treatment plan or drug doses.
- If something is missing, list it as a gap — do not guess.
- This is a briefing for the treating physician, not a second opinion to show the patient.
PROMPT;

        $userPrompt = "Doctor specialty: ".($specialty !== '' ? $specialty : 'unspecified')."\n\n"
            .$this->charts->promptBlock($snapshot, 8)
            ."\n\nReturn JSON only.";

        try {
            $completed = $this->ai->completeJsonObject($system, $userPrompt);
            $payload = $this->normalize($completed['payload'] ?? []);

            return array_merge($payload, [
                'empty_chart' => false,
                'chart' => $snapshot,
                'ai_provider' => $completed['ai_provider'] ?? 'unknown',
                'used_fallback' => false,
            ]);
        } catch (Throwable $e) {
            return array_merge($this->offlineBriefing($snapshot), [
                'empty_chart' => false,
                'chart' => $snapshot,
                'ai_provider' => 'fallback',
                'used_fallback' => true,
                'fallback_reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $title = trim((string) ($payload['briefing_title'] ?? 'Clinical briefing'));
        $summary = trim((string) ($payload['clinical_summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Chart loaded. Review the visit list below — the model did not return a narrative.';
        }

        return [
            'briefing_title' => $title !== '' ? $title : 'Clinical briefing',
            'clinical_summary' => $summary,
            'risk_flags' => $this->stringList($payload['risk_flags'] ?? []),
            'visit_highlights' => $this->highlightList($payload['visit_highlights'] ?? []),
            'medication_watch' => $this->stringList($payload['medication_watch'] ?? []),
            'suggested_focus' => $this->stringList($payload['suggested_focus'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function offlineBriefing(array $snapshot): array
    {
        $highlights = [];
        foreach (array_slice((array) ($snapshot['visits'] ?? []), 0, 4) as $visit) {
            $highlights[] = [
                'date' => (string) ($visit['visit_date'] ?? ''),
                'diagnosis' => (string) ($visit['diagnosis'] ?? ''),
                'note' => (string) ($visit['chief_complaint'] ?? ''),
            ];
        }

        $meds = [];
        foreach (array_slice((array) ($snapshot['medications'] ?? []), 0, 6) as $med) {
            $meds[] = (string) ($med['name'] ?? '');
        }

        return [
            'briefing_title' => 'Chart overview (offline)',
            'clinical_summary' => implode("\n", [
                '**AI briefing is temporarily unavailable.** Below is a structured readout of the visible chart so you can still prepare for the visit.',
                '',
                'Review the latest diagnoses, current medicines, and any gaps (allergies are not stored as a dedicated field in this chart).',
            ]),
            'risk_flags' => [],
            'visit_highlights' => $highlights,
            'medication_watch' => array_values(array_filter($meds)),
            'suggested_focus' => [
                'Reconcile current medicines with the latest visit.',
                'Ask what changed since the last diagnosis.',
                'Confirm whether new symptoms overlap prior complaints.',
            ],
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['text'] ?? $item['note'] ?? implode(' ', $item);
            }
            $text = trim((string) $item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values(array_slice($out, 0, 8));
    }

    /**
     * @param  mixed  $value
     * @return list<array{date: string, diagnosis: string, note: string}>
     */
    private function highlightList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'date' => trim((string) ($row['date'] ?? '')),
                'diagnosis' => trim((string) ($row['diagnosis'] ?? '')),
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        return array_values(array_slice($out, 0, 6));
    }
}
