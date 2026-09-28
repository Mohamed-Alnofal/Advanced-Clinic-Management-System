<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Log;
use Modules\Core\Jobs\SummarizeEmergencyTriageJob;
use Modules\Core\Models\EmergencySosSignal;

class EmergencyTriageService
{
    public function __construct(
        private EmergencyAiAnalyzerService $aiAnalyzer,
        private BreakGlassService $breakGlassService,
    ) {}

    /**
     * AI triage only: analyze transcript, save signal, return first-aid guidance.
     * Ambulance dispatch is a separate patient action (POST /ambulance/sos or request-ambulance).
     */
    public function process(array $payload, ?int $patientId = null, ?string $ip = null, ?int $userId = null): array
    {
        $transcript = $payload['text_transcript'];
        $usedFallback = false;
        $fallbackReason = null;

        try {
            $analysis = $this->aiAnalyzer->analyze($transcript, [
                'patient_id' => $patientId,
            ]);
        } catch (\Throwable $exception) {
            $fallbackReason = $exception->getMessage();
            $this->aiAnalyzer->logFailure($exception, $transcript, [
                'patient_id' => $patientId,
                'latitude' => $payload['latitude'],
                'longitude' => $payload['longitude'],
            ]);

            $analysis = $this->aiAnalyzer->fallbackAnalysis($transcript);
            $analysis['fallback_reason'] = $analysis['fallback_reason'] ?? $fallbackReason;
            $usedFallback = true;

            Log::warning('Emergency triage used fallback analysis', [
                'patient_id' => $patientId,
                'fallback_reason' => $analysis['fallback_reason'] ?? null,
            ]);
        }

        $this->aiAnalyzer->logAnalytics($analysis, $transcript, $usedFallback);

        $signal = EmergencySosSignal::create([
            'patient_id' => $patientId,
            'latitude' => $payload['latitude'],
            'longitude' => $payload['longitude'],
            'raw_transcript' => $transcript,
            'severity_level' => $analysis['severity_level'],
            'suspected_condition' => $analysis['suspected_condition'],
            'detected_category' => $analysis['detected_category'] ?? null,
            'first_aid_steps' => $analysis['immediate_first_aid_steps'],
            'warning_signs' => $analysis['warning_signs'] ?? [],
            'do_not' => $analysis['do_not'] ?? [],
            'ai_confidence' => $analysis['ai_confidence'] ?? null,
            'ai_source' => $analysis['ai_source'] ?? null,
            'call_emergency_now' => $analysis['call_emergency_now'] ?? false,
            'rules_score' => $analysis['rules_score'] ?? null,
            'created_at' => now(),
        ]);

        $snapshot = $this->breakGlassService->createSnapshot($signal, $ip);

        SummarizeEmergencyTriageJob::dispatch($signal->id, $transcript, $patientId);

        return [
            'signal' => $signal,
            'used_fallback' => $usedFallback,
            'analysis' => $analysis,
            'medical_snapshot_id' => $snapshot?->id,
            'emergency_request' => null,
            'dispatch' => null,
        ];
    }
}
