<?php

namespace Modules\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AI\Http\Requests\AnalyzeChartRequest;
use Modules\AI\Http\Requests\MedicalAiChatRequest;
use Modules\AI\Services\MedicalAiChartAssembler;
use Modules\AI\Services\MedicalAiClinicalBriefingService;
use Modules\AI\Services\UnifiedMedicalAiService;
use Modules\Core\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Unified medical chatbot (emergency + drug/symptom Q&A).
 * Never auto-dispatches ambulances.
 */
class UnifiedAiChatController extends Controller
{
    public function __construct(
        private UnifiedMedicalAiService $aiService,
        private MedicalAiChartAssembler $charts,
        private MedicalAiClinicalBriefingService $briefings,
    ) {}

    public function chat(MedicalAiChatRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->aiService->chat(
            (string) $data['message'],
            $request->user(),
            isset($data['conversation_id']) ? (string) $data['conversation_id'] : null,
            filter_var($data['include_chart'] ?? false, FILTER_VALIDATE_BOOL),
        );

        return response()->json([
            'success' => true,
            'message' => $result['used_fallback']
                ? 'Returned safe offline medical guidance.'
                : 'AI response generated successfully.',
            'data' => [
                'reply_text' => $result['reply_text'],
                'severity' => $result['severity'],
                'intent_type' => $result['intent_type'],
                'suggested_action' => $result['suggested_action'],
                // Legacy aliases — existing Flutter clients keep working.
                'suggest_sos' => (bool) $result['suggest_sos'],
                'severity_level' => $result['severity_level'],
                'recommended_action' => $result['recommended_action'],
                'conversation_id' => $result['conversation_id'],
                'ai_provider' => $result['ai_provider'],
                'used_fallback' => (bool) $result['used_fallback'],
                'auto_dispatch_ambulance' => false,
                'chart_attached' => (bool) ($result['chart_attached'] ?? false),
                'suggested_doctors' => $result['suggested_doctors'] ?? [],
                'matched_specialization' => $result['matched_specialization'] ?? null,
            ],
            'meta' => [
                'disclaimer' => 'This guidance is educational/first-aid only and does not replace professional medical care or emergency services.',
                'schema' => 'unified_v1',
            ],
        ]);
    }

    public function myChart(): JsonResponse
    {
        $user = request()->user();
        $snapshot = $this->charts->forAuthenticatedPatient($user);

        return response()->json([
            'success' => true,
            'data' => $snapshot,
            'meta' => [
                'disclaimer' => 'This is a glimpse of visits written by your doctors. It is not a diagnosis.',
            ],
        ]);
    }

    public function analyzeChart(AnalyzeChartRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $briefing = $this->briefings->brief(
                $request->user(),
                (int) $data['lookup_id'],
                (string) $data['source'],
            );
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not analyze this chart right now.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => ($briefing['used_fallback'] ?? false)
                ? 'Returned a structured chart readout.'
                : 'Clinical briefing generated.',
            'data' => $briefing,
            'meta' => [
                'disclaimer' => 'Clinical copilot only. It does not replace the treating physician’s judgment.',
                'schema' => 'doctor_briefing_v1',
            ],
        ]);
    }

    public function aiHealth(): JsonResponse
    {
        $status = $this->aiService->healthStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ], ($status['ready'] ?? false) ? 200 : 503);
    }
}
