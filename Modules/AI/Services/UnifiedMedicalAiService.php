<?php

namespace Modules\AI\Services;

use App\Support\GroqChatClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Drug\Models\Drug;
use Modules\MedicalVisits\Models\VitalSign;
use Modules\Patients\Models\PatientFile;
use Modules\Users\Models\User;
use Throwable;

/**
 * Unified medical companion: emergency first-aid + drug/symptom Q&A.
 *
 * Never auto-dispatches ambulances. UI actions are returned as flags only.
 */
class UnifiedMedicalAiService
{
    /** Cloud LLM HTTP timeout (Hostinger → Gemini/Groq/OpenAI). */
    private const CLOUD_TIMEOUT_SECONDS = 28;

    private const ALLOWED_SEVERITIES = ['CRITICAL', 'MODERATE', 'INFORMATIONAL'];

    private const ALLOWED_INTENTS = ['EMERGENCY', 'DRUG_INQUIRY', 'DOCTOR_INQUIRY', 'GENERAL'];

    private const ALLOWED_ACTION_TYPES = [
        'SHOW_SOS_BUTTON',
        'SHOW_BOOK_DOCTOR_BUTTON',
        'SHOW_PHARMACY_BUTTON',
        'NONE',
    ];

    /**
     * @return array{
     *   reply_text: string,
     *   severity: string,
     *   intent_type: string,
     *   suggested_action: array{type: string, payload: array{specialty_suggested: ?string, drug_name: ?string}},
     *   suggest_sos: bool,
     *   severity_level: string,
     *   recommended_action: string,
     *   conversation_id: string,
     *   ai_provider: string,
     *   used_fallback: bool,
     *   auto_dispatch_ambulance: false,
     *   chart_attached: bool,
     *   suggested_doctors: list<array<string, mixed>>,
     *   matched_specialization: ?array{id: int, name: string}
     * }
     */
    public function chat(string $message, ?User $user = null, ?string $conversationId = null, bool $includeChart = false): array
    {
        $conversationId = $conversationId !== null && trim($conversationId) !== ''
            ? trim($conversationId)
            : bin2hex(random_bytes(8));

        $patientContext = $this->buildPatientContext($user);
        $chartAttached = false;
        if ($includeChart && $user) {
            try {
                $chart = app(MedicalAiChartAssembler::class)->forAuthenticatedPatient($user);
                $block = app(MedicalAiChartAssembler::class)->promptBlock($chart);
                if ($block !== '') {
                    $patientContext = trim($patientContext."\n".$block);
                    $chartAttached = true;
                }
            } catch (Throwable $e) {
                Log::warning('Unified AI: chart attach failed', ['error' => $e->getMessage()]);
            }
        }
        $drugContext = $this->buildDrugContext($message);
        $errors = [];

        $chain = $this->cloudProviderChain();
        if ($chain === []) {
            Log::error('Unified AI: no cloud provider configured (set GEMINI_API_KEY and/or GROQ_API_KEY on server)');
        }

        foreach ($chain as $provider) {
            try {
                $payload = match ($provider) {
                    'gemini' => $this->callGemini($message, $patientContext, $drugContext),
                    'groq' => $this->callGroq($message, $patientContext, $drugContext),
                    'openai' => $this->callOpenAi($message, $patientContext, $drugContext),
                    default => throw new \RuntimeException("Unsupported provider: {$provider}"),
                };
                $normalized = $this->normalizePayload($payload);

                return $this->finalizeChatResult($normalized, $conversationId, $provider, false, $chartAttached, $message);
            } catch (Throwable $e) {
                $errors[] = "{$provider}: ".$e->getMessage();
                Log::warning("Unified AI: {$provider} failed", ['error' => $e->getMessage()]);
            }
        }

        Log::error('Unified AI: all cloud providers failed, using offline fallback', [
            'errors' => $errors,
        ]);

        // Safe offline template (never HTTP 500) — only when every cloud provider fails.
        return $this->finalizeChatResult(
            $this->offlineFallback($message),
            $conversationId,
            'fallback',
            true,
            $chartAttached,
            $message
        );
    }

    /**
     * Shared cloud JSON completion for sibling AI features (clinical briefing).
     *
     * @return array{payload: array<string, mixed>, ai_provider: string}
     */
    public function completeJsonObject(string $systemPrompt, string $userPrompt): array
    {
        $chain = $this->cloudProviderChain();
        if ($chain === []) {
            throw new \RuntimeException('No cloud AI provider configured.');
        }

        $errors = [];
        foreach ($chain as $provider) {
            try {
                $payload = match ($provider) {
                    'gemini' => $this->callGeminiCustom($systemPrompt, $userPrompt),
                    'groq' => $this->callGroqCustom($systemPrompt, $userPrompt),
                    'openai' => $this->callOpenAiCustom($systemPrompt, $userPrompt),
                    default => throw new \RuntimeException("Unsupported provider: {$provider}"),
                };

                return ['payload' => $payload, 'ai_provider' => $provider];
            } catch (Throwable $e) {
                $errors[] = "{$provider}: ".$e->getMessage();
                Log::warning("Unified AI completeJson: {$provider} failed", ['error' => $e->getMessage()]);
            }
        }

        throw new \RuntimeException('All cloud providers failed: '.implode(' | ', $errors));
    }

    public function healthStatus(): array
    {
        $chain = $this->cloudProviderChain();
        $checks = [];

        foreach ($chain as $provider) {
            $checks[$provider] = $this->probeProvider($provider);
        }

        return [
            'provider_chain' => $chain,
            'providers' => $checks,
            'ready' => $chain !== [] && collect($checks)->contains(fn ($row) => ($row['ok'] ?? false) === true),
        ];
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function probeProvider(string $provider): array
    {
        try {
            return match ($provider) {
                'groq' => $this->probeGroq(),
                'gemini' => $this->probeGemini(),
                'openai' => $this->probeOpenAi(),
                default => ['ok' => false, 'detail' => 'unknown provider'],
            };
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function probeGroq(): array
    {
        $probe = app(GroqChatClient::class)->probeHealth();

        return [
            'ok' => (bool) ($probe['ok'] ?? false),
            'detail' => (string) ($probe['detail'] ?? 'unknown'),
            'model_chain' => $probe['model_chain'] ?? [],
            'models' => $probe['models'] ?? [],
        ];
    }

    /** @return array{ok: bool, detail: string} */
    private function probeGemini(): array
    {
        $apiKey = trim((string) config('services.emergency_ai.gemini_api_key'));
        if (! $this->hasUsableApiKey($apiKey)) {
            return ['ok' => false, 'detail' => 'GEMINI_API_KEY missing'];
        }

        $model = (string) config('services.emergency_ai.gemini_model', 'gemini-2.0-flash');
        $response = Http::timeout(8)
            ->acceptJson()
            ->get("https://generativelanguage.googleapis.com/v1beta/models/{$model}?key={$apiKey}");

        return [
            'ok' => $response->successful(),
            'detail' => $response->successful() ? 'reachable' : 'HTTP '.$response->status(),
        ];
    }

    /** @return array{ok: bool, detail: string} */
    private function probeOpenAi(): array
    {
        $apiKey = trim((string) config('services.emergency_ai.openai_api_key'));
        if (! $this->hasUsableApiKey($apiKey)) {
            return ['ok' => false, 'detail' => 'OPENAI_API_KEY missing'];
        }

        $response = Http::timeout(8)
            ->withToken($apiKey)
            ->acceptJson()
            ->get('https://api.openai.com/v1/models');

        return [
            'ok' => $response->successful(),
            'detail' => $response->successful() ? 'reachable' : 'HTTP '.$response->status(),
        ];
    }

    /**
     * Production order: cloud APIs only (no local Ollama on shared hosting).
     *
     * @return list<string>
     */
    private function cloudProviderChain(): array
    {
        $raw = config('services.medical_ai.providers', 'groq,gemini,openai');
        $candidates = is_string($raw)
            ? array_map('trim', explode(',', $raw))
            : (array) $raw;

        $out = [];
        foreach ($candidates as $provider) {
            $provider = strtolower(trim((string) $provider));
            if ($provider === '' || $provider === 'ollama') {
                continue;
            }
            if (! in_array($provider, ['gemini', 'groq', 'openai'], true)) {
                continue;
            }
            if (! $this->providerReady($provider)) {
                continue;
            }
            if (! in_array($provider, $out, true)) {
                $out[] = $provider;
            }
        }

        return $out;
    }

    private function providerReady(string $provider): bool
    {
        return match ($provider) {
            'gemini' => $this->hasUsableApiKey((string) config('services.emergency_ai.gemini_api_key')),
            'groq' => $this->hasUsableApiKey((string) config('services.groq.key')),
            'openai' => $this->hasUsableApiKey((string) config('services.emergency_ai.openai_api_key')),
            default => false,
        };
    }

    private function hasUsableApiKey(string $key): bool
    {
        $key = trim($key);

        return $key !== '' && ! str_contains(strtoupper($key), 'PASTE_YOUR');
    }

    /**
     * @return array<string, mixed>
     */
    private function callGroq(string $message, string $patientContext, string $drugContext): array
    {
        $content = app(GroqChatClient::class)->complete(
            $this->buildMessages($message, $patientContext, $drugContext),
            self::CLOUD_TIMEOUT_SECONDS
        );

        return $this->decodeJsonObject($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function callGemini(string $message, string $patientContext, string $drugContext): array
    {
        $apiKey = trim((string) config('services.emergency_ai.gemini_api_key', ''));
        $model = (string) config('services.emergency_ai.gemini_model', 'gemini-2.0-flash');

        if ($apiKey === '' || str_contains(strtoupper($apiKey), 'PASTE_YOUR')) {
            throw new \RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $prompt = $this->systemPrompt()."\n\n".$this->buildUserPrompt($message, $patientContext, $drugContext);

        $response = Http::timeout(self::CLOUD_TIMEOUT_SECONDS)
            ->connectTimeout(8)
            ->acceptJson()
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        $response->throw();

        $content = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        return $this->decodeJsonObject($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function callOpenAi(string $message, string $patientContext, string $drugContext): array
    {
        $apiKey = trim((string) config('services.emergency_ai.openai_api_key', ''));
        $model = (string) config('services.emergency_ai.openai_model', 'gpt-4o-mini');

        if (! $this->hasUsableApiKey($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::timeout(self::CLOUD_TIMEOUT_SECONDS)
            ->connectTimeout(8)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => $this->buildMessages($message, $patientContext, $drugContext),
            ]);

        $response->throw();

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI returned an empty response.');
        }

        return $this->decodeJsonObject($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function callGroqCustom(string $systemPrompt, string $userPrompt): array
    {
        $content = app(GroqChatClient::class)->complete(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            self::CLOUD_TIMEOUT_SECONDS
        );

        return $this->decodeJsonObject($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function callGeminiCustom(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = trim((string) config('services.emergency_ai.gemini_api_key', ''));
        $model = (string) config('services.emergency_ai.gemini_model', 'gemini-2.0-flash');

        if ($apiKey === '' || str_contains(strtoupper($apiKey), 'PASTE_YOUR')) {
            throw new \RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $response = Http::timeout(self::CLOUD_TIMEOUT_SECONDS)
            ->connectTimeout(8)
            ->acceptJson()
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $systemPrompt."\n\n".$userPrompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        $response->throw();

        $content = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Gemini returned an empty response.');
        }

        return $this->decodeJsonObject($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function callOpenAiCustom(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = trim((string) config('services.emergency_ai.openai_api_key', ''));
        $model = (string) config('services.emergency_ai.openai_model', 'gpt-4o-mini');

        if (! $this->hasUsableApiKey($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $response = Http::timeout(self::CLOUD_TIMEOUT_SECONDS)
            ->connectTimeout(8)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        $response->throw();

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI returned an empty response.');
        }

        return $this->decodeJsonObject($content);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(string $message, string $patientContext, string $drugContext): array
    {
        return [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->buildUserPrompt($message, $patientContext, $drugContext)],
        ];
    }

    private function buildUserPrompt(string $message, string $patientContext, string $drugContext): string
    {
        $userPrompt = "Patient message:\n{$message}\n";
        if ($patientContext !== '') {
            $userPrompt .= "\nPatient metadata (use carefully, do not invent):\n{$patientContext}\n";
        }
        if ($drugContext !== '') {
            $userPrompt .= "\nPharmacy catalog hints (educational only):\n{$drugContext}\n";
        }
        try {
            $catalog = app(MedicalAiDoctorMatcher::class)->catalogPromptBlock();
            if ($catalog !== '') {
                $userPrompt .= "\n{$catalog}\n";
            }
        } catch (Throwable) {
            // Catalog is optional — chat must still work.
        }
        $userPrompt .= "\nMatch reply_text language to the patient's message language (Arabic or English). Return ONLY valid JSON.";

        return $userPrompt;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a Unified AI Medical & Emergency Assistant for a certified hospital management system.
Your job is to analyze the user message and return a strictly validated JSON response matching this schema:

{
  "reply_text": "Detailed, markdown-formatted answer. If emergency: bulleted immediate first-aid steps. If drug/symptom: empathetic educational explanation.",
  "severity": "CRITICAL" | "MODERATE" | "INFORMATIONAL",
  "intent_type": "EMERGENCY" | "DRUG_INQUIRY" | "DOCTOR_INQUIRY" | "GENERAL",
  "suggested_action": {
    "type": "SHOW_SOS_BUTTON" | "SHOW_BOOK_DOCTOR_BUTTON" | "SHOW_PHARMACY_BUTTON" | "NONE",
    "payload": {
      "specialty_suggested": "string or null",
      "drug_name": "string or null"
    }
  }
}

RULES:
1. NEVER trigger automatic ambulance dispatches. If CRITICAL, set intent_type to EMERGENCY and suggested_action.type to SHOW_SOS_BUTTON.
2. Provide step-by-step first-aid protocols for CRITICAL inputs immediately.
3. For drugs/symptoms: Provide clear info, remind them to consult a physician, and suggest the right specialty/drug in payload when relevant. Do NOT write definitive prescriptions or exact dosing orders.
4. Never claim an ambulance was already sent.
5. Prefer Arabic when the user writes Arabic; otherwise match the user language.
6. When recommending a clinic visit, set specialty_suggested to the closest exact name from the provided specialization catalog. Do not invent a specialty that is not listed.
7. If a patient chart is provided, use it as background only. Mention relevant history briefly. Never recite the whole chart and never treat it as a new diagnosis.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   reply_text: string,
     *   severity: string,
     *   intent_type: string,
     *   suggested_action: array{type: string, payload: array{specialty_suggested: ?string, drug_name: ?string}},
     *   suggest_sos: bool,
     *   severity_level: string,
     *   recommended_action: string
     * }
     */
    private function normalizePayload(array $payload): array
    {
        $reply = trim((string) ($payload['reply_text'] ?? ''));
        if ($reply === '') {
            throw new \RuntimeException('AI JSON missing reply_text.');
        }

        $severity = strtoupper(trim((string) (
            $payload['severity']
            ?? $payload['severity_level']
            ?? 'INFORMATIONAL'
        )));
        if (! in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            $severity = 'INFORMATIONAL';
        }

        $intent = strtoupper(trim((string) ($payload['intent_type'] ?? 'GENERAL')));
        if (! in_array($intent, self::ALLOWED_INTENTS, true)) {
            $intent = 'GENERAL';
        }

        $actionRaw = $payload['suggested_action'] ?? null;
        $actionType = 'NONE';
        $specialty = null;
        $drugName = null;

        if (is_array($actionRaw)) {
            $actionType = strtoupper(trim((string) ($actionRaw['type'] ?? 'NONE')));
            $inner = $actionRaw['payload'] ?? [];
            if (is_array($inner)) {
                $specialty = $this->nullableString($inner['specialty_suggested'] ?? null);
                $drugName = $this->nullableString($inner['drug_name'] ?? null);
            }
        }

        // Legacy field mapping if model still returns old keys.
        if ($actionType === 'NONE' || ! in_array($actionType, self::ALLOWED_ACTION_TYPES, true)) {
            $legacy = strtoupper(trim((string) ($payload['recommended_action'] ?? '')));
            $actionType = match ($legacy) {
                'CALL_SOS' => 'SHOW_SOS_BUTTON',
                'BOOK_DOCTOR' => 'SHOW_BOOK_DOCTOR_BUTTON',
                'BUY_DRUG' => 'SHOW_PHARMACY_BUTTON',
                default => in_array($actionType, self::ALLOWED_ACTION_TYPES, true) ? $actionType : 'NONE',
            };
        }

        if (! in_array($actionType, self::ALLOWED_ACTION_TYPES, true)) {
            $actionType = 'NONE';
        }

        if ($severity === 'CRITICAL' || filter_var($payload['suggest_sos'] ?? false, FILTER_VALIDATE_BOOL)) {
            $severity = 'CRITICAL';
            $intent = 'EMERGENCY';
            $actionType = 'SHOW_SOS_BUTTON';
        }

        $legacyAction = match ($actionType) {
            'SHOW_SOS_BUTTON' => 'CALL_SOS',
            'SHOW_BOOK_DOCTOR_BUTTON' => 'BOOK_DOCTOR',
            'SHOW_PHARMACY_BUTTON' => 'BUY_DRUG',
            default => 'NONE',
        };

        return [
            'reply_text' => $reply,
            'severity' => $severity,
            'intent_type' => $intent,
            'suggested_action' => [
                'type' => $actionType,
                'payload' => [
                    'specialty_suggested' => $specialty,
                    'drug_name' => $drugName,
                ],
            ],
            // Backward-compatible aliases for existing Flutter clients.
            'suggest_sos' => $actionType === 'SHOW_SOS_BUTTON',
            'severity_level' => $severity,
            'recommended_action' => $legacyAction,
        ];
    }

    /**
     * @return array{
     *   reply_text: string,
     *   severity: string,
     *   intent_type: string,
     *   suggested_action: array{type: string, payload: array{specialty_suggested: ?string, drug_name: ?string}},
     *   suggest_sos: bool,
     *   severity_level: string,
     *   recommended_action: string
     * }
     */
    private function offlineFallback(string $message): array
    {
        if ($this->looksLikeEmergency($message)) {
            return [
                'reply_text' => implode("\n", [
                    '**إرشاد إسعافي أولي (وضع عدم اتصال بالـ AI)**',
                    '',
                    '1. تأكد من سلامة المكان وابتعد عن أي خطر مباشر.',
                    '2. تحقق من وعي المصاب وتنفسه واستجابته.',
                    '3. عند توقف التنفس أو غياب النبض: ابدأ إنعاشاً قلبياً رئوياً إن كنت مدرباً.',
                    '4. أوقف النزيف الظاهر بضغط مباشر بقطعة قماش نظيفة.',
                    '5. لا تعطِ طعاماً أو شراباً لفاقد الوعي.',
                    '6. اطلب المساعدة فوراً عبر زر طلب الإسعاف في التطبيق أو اتصل 140/112.',
                    '',
                    '_هذا إرشاد أولي ولا يغني عن التقييم الطبي المباشر._',
                ]),
                'severity' => 'CRITICAL',
                'intent_type' => 'EMERGENCY',
                'suggested_action' => [
                    'type' => 'SHOW_SOS_BUTTON',
                    'payload' => [
                        'specialty_suggested' => null,
                        'drug_name' => null,
                    ],
                ],
                'suggest_sos' => true,
                'severity_level' => 'CRITICAL',
                'recommended_action' => 'CALL_SOS',
            ];
        }

        return [
            'reply_text' => implode("\n", [
                '**المساعد الطبي غير متاح مؤقتاً**',
                '',
                'يمكنك:',
                '- حجز موعد مع طبيب من التطبيق.',
                '- البحث عن دواء من قسم الصيدليات.',
                '- إذا كانت الحالة طارئة: استخدم زر طلب الإسعاف.',
                '',
                '_لا تعتمد على التخمين. راجع مختصاً عند الحاجة._',
            ]),
            'severity' => 'INFORMATIONAL',
            'intent_type' => 'GENERAL',
            'suggested_action' => [
                'type' => 'SHOW_BOOK_DOCTOR_BUTTON',
                'payload' => [
                    'specialty_suggested' => null,
                    'drug_name' => null,
                ],
            ],
            'suggest_sos' => false,
            'severity_level' => 'INFORMATIONAL',
            'recommended_action' => 'BOOK_DOCTOR',
        ];
    }

    private function looksLikeEmergency(string $message): bool
    {
        $m = mb_strtolower($message);
        $keys = [
            'صدر', 'اختناق', 'نزيف', 'فاقد', 'غيبوبة', 'جلطة', 'سكتة', 'تشنج',
            'حساسية شديدة', 'غرق', 'حرق', 'لا يتنفس', 'توقف القلب', 'قلب', 'هيرت',
            'chest', 'chok', 'bleed', 'unconscious', 'stroke', 'seizure', 'anaphyla', 'cpr', 'heart',
        ];
        foreach ($keys as $k) {
            if (str_contains($m, $k)) {
                return true;
            }
        }

        return false;
    }

    private function buildPatientContext(?User $user): string
    {
        if ($user === null) {
            return '';
        }

        $parts = [];
        if ($user->age !== null && $user->age !== '') {
            $parts[] = 'Age: '.$user->age;
        }
        if (! empty($user->gender)) {
            $parts[] = 'Gender: '.$user->gender;
        }

        $patient = $user->patient;
        if ($patient) {
            $file = PatientFile::where('patient_id', $patient->id)->first();
            if ($file) {
                $vital = VitalSign::where('patient_file_id', $file->id)->latest('id')->first();
                if ($vital) {
                    if (! empty($vital->blood_type)) {
                        $parts[] = 'Blood group: '.$vital->blood_type.(! empty($vital->rh_factor) ? $vital->rh_factor : '');
                    }
                    if ($vital->bmi !== null) {
                        $parts[] = 'BMI: '.$vital->bmi;
                    }
                }
            }
        }

        return implode(' | ', $parts);
    }

    private function buildDrugContext(string $message): string
    {
        $tokens = preg_split('/\s+/u', trim($message)) ?: [];
        $candidates = [];
        foreach ($tokens as $token) {
            $token = trim($token, ".,!?()[]\"'");
            if (mb_strlen($token) >= 3) {
                $candidates[] = $token;
            }
        }
        $candidates = array_slice(array_unique($candidates), 0, 4);
        if ($candidates === []) {
            return '';
        }

        try {
            $query = Drug::query()->select(['id', 'name', 'description', 'indication']);
            $query->where(function ($q) use ($candidates) {
                foreach ($candidates as $word) {
                    $q->orWhere('name', 'like', '%'.$word.'%');
                }
            });
            $rows = $query->limit(3)->get();
        } catch (Throwable) {
            return '';
        }

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($rows as $drug) {
            $lines[] = sprintf(
                '- %s: %s',
                $drug->name,
                mb_substr((string) ($drug->indication ?: $drug->description ?: 'listed in pharmacy catalog'), 0, 160)
            );
        }

        return implode("\n", $lines);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' || strtolower($text) === 'null' ? null : $text;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function finalizeChatResult(
        array $normalized,
        string $conversationId,
        string $provider,
        bool $usedFallback,
        bool $chartAttached,
        string $message,
    ): array {
        $action = (string) ($normalized['suggested_action']['type'] ?? 'NONE');
        $intent = (string) ($normalized['intent_type'] ?? 'GENERAL');
        $specialty = $normalized['suggested_action']['payload']['specialty_suggested'] ?? null;
        $doctors = [];
        $matched = null;

        if ($action === 'SHOW_BOOK_DOCTOR_BUTTON' || $intent === 'DOCTOR_INQUIRY') {
            try {
                $match = app(MedicalAiDoctorMatcher::class)->suggest(
                    is_string($specialty) ? $specialty : null,
                    $message
                );
                $doctors = $match['doctors'] ?? [];
                $matched = $match['specialization'] ?? null;
            } catch (Throwable $e) {
                Log::warning('Unified AI: doctor match failed', ['error' => $e->getMessage()]);
            }
        }

        return array_merge($normalized, [
            'conversation_id' => $conversationId,
            'ai_provider' => $provider,
            'used_fallback' => $usedFallback,
            'auto_dispatch_ambulance' => false,
            'chart_attached' => $chartAttached,
            'suggested_doctors' => $doctors,
            'matched_specialization' => $matched,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $jsonText): array
    {
        $jsonText = trim($jsonText);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $jsonText, $matches)) {
            $jsonText = trim($matches[1]);
        }

        $payload = json_decode($jsonText, true);
        if (! is_array($payload)) {
            throw new \RuntimeException('AI response is not valid JSON.');
        }

        return $payload;
    }
}
