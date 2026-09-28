<?php

namespace Modules\AI\Services;

use Modules\Users\Models\User;

/**
 * @deprecated Prefer UnifiedMedicalAiService. Kept as a thin adapter so existing DI keeps working.
 */
class GroqMedicalAiService
{
    public function __construct(
        private UnifiedMedicalAiService $unified,
    ) {}

    /**
     * @return array{
     *   reply_text: string,
     *   suggest_sos: bool,
     *   severity_level: string,
     *   recommended_action: string,
     *   conversation_id: string,
     *   ai_provider: string,
     *   used_fallback: bool
     * }
     */
    public function chat(string $message, ?User $user = null, ?string $conversationId = null): array
    {
        $result = $this->unified->chat($message, $user, $conversationId);

        return [
            'reply_text' => $result['reply_text'],
            'suggest_sos' => (bool) $result['suggest_sos'],
            'severity_level' => $result['severity_level'],
            'recommended_action' => $result['recommended_action'],
            'conversation_id' => $result['conversation_id'],
            'ai_provider' => $result['ai_provider'],
            'used_fallback' => (bool) $result['used_fallback'],
        ];
    }
}
