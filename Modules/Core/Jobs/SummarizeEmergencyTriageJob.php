<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Ambulance\Models\EmergencyRequest;
use Modules\Core\Models\EmergencySosSignal;
use Modules\Core\Services\EmergencyAiAnalyzerService;

/**
 * Secondary pass: if the first answer was weak (fallback / unknown),
 * refine the stored signal (and linked request snapshot) when a better analysis arrives.
 */
class SummarizeEmergencyTriageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(
        public int $signalId,
        public string $transcript,
        public ?int $patientId = null,
    ) {}

    public function handle(EmergencyAiAnalyzerService $aiAnalyzer): void
    {
        $signal = EmergencySosSignal::find($this->signalId);
        if (! $signal) {
            return;
        }

        try {
            $summary = $aiAnalyzer->analyze($this->transcript, [
                'patient_id' => $this->patientId ?? $signal->patient_id,
            ]);

            if (! $this->isBetter($signal, $summary)) {
                Log::info('Emergency triage secondary analysis kept original', [
                    'signal_id' => $signal->id,
                    'ai_source' => $summary['ai_source'] ?? null,
                ]);

                return;
            }

            $signal->update([
                'severity_level' => $summary['severity_level'],
                'suspected_condition' => $summary['suspected_condition'],
                'detected_category' => $summary['detected_category'] ?? $signal->detected_category,
                'first_aid_steps' => $summary['immediate_first_aid_steps'],
                'warning_signs' => $summary['warning_signs'] ?? $signal->warning_signs,
                'do_not' => $summary['do_not'] ?? $signal->do_not,
                'ai_confidence' => $summary['ai_confidence'] ?? $signal->ai_confidence,
                'ai_source' => $summary['ai_source'] ?? $signal->ai_source,
                'call_emergency_now' => $summary['call_emergency_now'] ?? $signal->call_emergency_now,
                'rules_score' => $summary['rules_score'] ?? $signal->rules_score,
            ]);

            EmergencyRequest::query()
                ->where('sos_signal_id', $signal->id)
                ->whereIn('status', ['pending', 'assigned', 'arrived'])
                ->update([
                    'severity_level' => $summary['severity_level'],
                    'suspected_condition' => $summary['suspected_condition'],
                    'first_aid_steps' => $summary['immediate_first_aid_steps'],
                    'priority' => $summary['severity_level'] === EmergencySosSignal::SEVERITY_CRITICAL
                        ? 'critical'
                        : ($summary['severity_level'] === EmergencySosSignal::SEVERITY_URGENT ? 'high' : null),
                ]);

            Log::info('Emergency triage secondary analysis updated signal', [
                'signal_id' => $signal->id,
                'ai_source' => $summary['ai_source'] ?? null,
                'category' => $summary['detected_category'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Emergency triage secondary analysis failed', [
                'signal_id' => $signal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isBetter(EmergencySosSignal $signal, array $summary): bool
    {
        $newSource = (string) ($summary['ai_source'] ?? '');
        $oldSource = (string) ($signal->ai_source ?? '');
        $newCategory = $summary['detected_category'] ?? null;
        $oldCategory = $signal->detected_category;

        $oldWeak = in_array($oldSource, ['fallback', 'fallback+rules'], true)
            || $oldCategory === null
            || $oldCategory === 'unknown'
            || ($signal->ai_confidence ?? '') === 'low';

        $newStrong = in_array($newSource, ['ai', 'ai_two_stage', 'clinical_rules'], true)
            && $newCategory !== null
            && $newCategory !== 'unknown';

        if ($oldWeak && $newStrong) {
            return true;
        }

        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $oldConf = $rank[$signal->ai_confidence ?? 'low'] ?? 1;
        $newConf = $rank[$summary['ai_confidence'] ?? 'low'] ?? 1;

        return $newConf > $oldConf
            && $newCategory !== null
            && $newCategory !== 'unknown';
    }
}
