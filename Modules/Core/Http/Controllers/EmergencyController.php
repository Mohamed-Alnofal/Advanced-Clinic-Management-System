<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Requests\EmergencyTriageRequest;
use Modules\Core\Models\EmergencySosSignal;
use Modules\Core\Services\EmergencyAiAnalyzerService;
use Modules\Core\Services\EmergencyTriageService;
use Modules\Users\Models\User;

class EmergencyController extends Controller
{
    public function __construct(
        private EmergencyTriageService $triageService,
        private \Modules\Core\Services\BreakGlassService $breakGlassService,
    ) {}

    public function aiHealth(EmergencyAiAnalyzerService $aiAnalyzer): JsonResponse
    {
        $status = $aiAnalyzer->healthStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ], ($status['ready'] ?? false) ? 200 : 503);
    }

    public function triage(EmergencyTriageRequest $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->triageService->process(
            $request->validated(),
            $this->resolvePatientId($user),
            $request->ip(),
            $user?->id,
        );

        $signal = $result['signal'];
        $severityLevel = $signal->severity_level;
        $emergencyRequest = $result['emergency_request'] ?? null;

        return response()->json([
            'success' => true,
            'message' => $result['used_fallback']
                ? 'تم حفظ الحالة الطارئة باستخدام تقييم أولي آمن. يُنصح بالاتصال بالطوارئ فوراً.'
                : 'تم تحليل الحالة الطارئة بنجاح',
            'data' => [
                'id' => $signal->id,
                'patient_id' => $signal->patient_id,
                'location' => [
                    'latitude' => (float) $signal->latitude,
                    'longitude' => (float) $signal->longitude,
                ],
                'severity_level' => $severityLevel,
                'severityLevel' => $severityLevel,
                'severity' => strtoupper($severityLevel),
                'severity_label_ar' => EmergencySosSignal::SEVERITY_LABELS_AR[$severityLevel] ?? $severityLevel,
                'suspected_condition' => $signal->suspected_condition,
                'detected_category' => $signal->detected_category,
                'first_aid_steps' => $signal->first_aid_steps,
                'warning_signs' => $signal->warning_signs ?? [],
                'do_not' => $signal->do_not ?? [],
                'ai_confidence' => $signal->ai_confidence,
                'ai_source' => $signal->ai_source,
                'call_emergency_now' => (bool) $signal->call_emergency_now,
                'rules_score' => $signal->rules_score,
                'ai_fallback_used' => $result['used_fallback'],
                'fallback_reason' => $this->publicFallbackReason($result['analysis']['fallback_reason'] ?? null),
                'retrieval_mode' => $result['analysis']['retrieval_mode'] ?? null,
                'matched_protocol' => $result['analysis']['matched_protocol'] ?? $signal->detected_category,
                'ai_provider' => $result['analysis']['ai_provider'] ?? null,
                'patient_context_used' => (bool) ($result['analysis']['patient_context_used'] ?? false),
                'created_at' => $signal->created_at?->toIso8601String(),
                'emergency_request_id' => $emergencyRequest?->id,
                'emergency_request' => $emergencyRequest,
                'dispatch' => $result['dispatch'] ?? null,
                'has_medical_snapshot' => \Modules\Core\Models\EmergencyMedicalSnapshot::where('sos_signal_id', $signal->id)->exists(),
            ],
            'meta' => [
                'disclaimer' => 'هذا التقييم إرشادي أولي ولا يُغني عن الفحص الطبي المباشر أو الاتصال بخدمات الطوارئ.',
            ],
        ]);
    }

    public function viewBreakGlass(int $sosSignalId, \Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        $snapshot = $this->breakGlassService->revealForUser(
            $sosSignalId,
            $request->user(),
            $request->input('reason'),
            $request->ip(),
        );

        return response()->json([
            'success' => true,
            'data' => $snapshot,
        ]);
    }

    private function resolvePatientId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        return $user->patient?->id;
    }

    private function publicFallbackReason(mixed $reason): ?string
    {
        if (! is_string($reason) || trim($reason) === '') {
            return null;
        }

        $raw = strtolower($reason);
        if (str_contains($raw, '429') || str_contains($raw, 'quota') || str_contains($raw, 'rate limit')) {
            return 'quota_exceeded';
        }
        if (str_contains($raw, '401') || str_contains($raw, '403') || str_contains($raw, 'api key') || str_contains($raw, 'api_key')) {
            return 'invalid_api_key';
        }
        if (str_contains($raw, 'timeout') || str_contains($raw, 'timed out') || str_contains($raw, 'connection')) {
            return 'provider_timeout';
        }

        return 'provider_unavailable';
    }
}
