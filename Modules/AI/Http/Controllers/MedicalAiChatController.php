<?php

namespace Modules\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AI\Http\Requests\MedicalAiChatRequest;
use Modules\AI\Services\UnifiedMedicalAiService;
use Modules\Core\Http\Controllers\Controller;

/**
 * Legacy medical chat endpoint — delegates to unified service.
 *
 * @deprecated Prefer UnifiedAiChatController / POST /api/v1/ai/chat
 */
class MedicalAiChatController extends Controller
{
    public function __construct(
        private UnifiedMedicalAiService $aiService,
    ) {}

    public function chat(MedicalAiChatRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->aiService->chat(
            (string) $data['message'],
            $request->user(),
            isset($data['conversation_id']) ? (string) $data['conversation_id'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => $result['used_fallback']
                ? 'Returned safe offline medical guidance.'
                : 'AI response generated successfully.',
            'data' => [
                'reply_text' => $result['reply_text'],
                'suggest_sos' => (bool) $result['suggest_sos'],
                'severity_level' => $result['severity_level'],
                'recommended_action' => $result['recommended_action'],
                'severity' => $result['severity'],
                'intent_type' => $result['intent_type'],
                'suggested_action' => $result['suggested_action'],
                'conversation_id' => $result['conversation_id'],
                'ai_provider' => $result['ai_provider'],
                'used_fallback' => (bool) $result['used_fallback'],
                'auto_dispatch_ambulance' => false,
            ],
            'meta' => [
                'disclaimer' => 'This guidance is educational/first-aid only and does not replace professional medical care or emergency services.',
            ],
        ]);
    }
}
