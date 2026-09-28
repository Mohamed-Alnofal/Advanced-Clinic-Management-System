<?php

namespace Modules\Core\Services;

use App\Support\GroqChatClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\EmergencySosSignal;
use RuntimeException;

class EmergencyAiAnalyzerService
{
    private const CLOUD_TIMEOUT_SECONDS = 15;

    private const ALLOWED_SEVERITIES = [
        EmergencySosSignal::SEVERITY_CRITICAL,
        EmergencySosSignal::SEVERITY_URGENT,
        EmergencySosSignal::SEVERITY_STABLE,
    ];

    private ?string $patientContextBlock = null;

    private ?string $lastFallbackReason = null;

    private ?string $lastProviderUsed = null;

    public function __construct(
        private EmergencyTranscriptNormalizer $normalizer,
        private EmergencyClinicalRulesService $clinicalRules,
        private EmergencyStepValidator $stepValidator,
        private EmergencyProtocolRetriever $protocolRetriever,
        private EmergencyPatientContextService $patientContext,
    ) {}

    /**
     * @param  array{patient_id?: int|null}  $options
     */
    public function analyze(string $transcript, array $options = []): array
    {
        $normalized = $this->normalizer->normalize($transcript);
        $this->patientContextBlock = $this->patientContext->buildPromptBlock(
            isset($options['patient_id']) ? (int) $options['patient_id'] : null
        );
        $this->lastFallbackReason = null;
        $this->lastProviderUsed = null;

        // Legacy/tests only: optional rules-first short-circuit (default OFF → AI-first).
        if (config('services.emergency_ai.rules_first_enabled', false)) {
            $rulesResult = $this->clinicalRules->resolve(
                $normalized,
                (int) config('services.emergency_ai.rules_override_score', 85)
            );

            if ($rulesResult !== null) {
                return $this->finalizeAnalysis($normalized, $rulesResult);
            }
        }

        $providers = $this->providerChain();
        $errors = [];

        foreach ($providers as $provider) {
            try {
                $analysis = $this->analyzeWithProvider($provider, $normalized);
                $this->lastProviderUsed = $provider;
                $final = $this->finalizeAnalysis($normalized, $analysis, 'ai');
                $final['ai_provider'] = $provider;
                $final['retrieval_mode'] = $this->protocolRetriever->lastRetrievalMode();
                $final['matched_protocol'] = $final['detected_category'] ?? null;
                if ($this->patientContextBlock !== null) {
                    $final['patient_context_used'] = true;
                }

                return $final;
            } catch (\Throwable $e) {
                $errors[$provider] = $e->getMessage();
                $this->logFailure($e, $transcript, [
                    'provider_attempt' => $provider,
                    'failover' => true,
                ]);
            }
        }

        $this->lastFallbackReason = 'all_providers_failed: '.implode(' | ', array_map(
            static fn ($p, $msg) => "{$p}={$msg}",
            array_keys($errors),
            array_values($errors)
        ));

        throw new RuntimeException($this->lastFallbackReason);
    }

    public function healthStatus(): array
    {
        $primary = (string) config('services.emergency_ai.provider', 'gemini');
        $chain = $this->providerChain();
        $checks = [];

        foreach ($chain as $provider) {
            $checks[$provider] = $this->probeProvider($provider);
        }

        $embeddingsAvailable = app(EmergencyEmbeddingService::class)->isAvailable();

        return [
            'primary_provider' => $primary,
            'provider_chain' => $chain,
            'providers' => $checks,
            'embeddings_available' => $embeddingsAvailable,
            'embeddings_enabled' => (bool) config('services.emergency_ai.embeddings_enabled', true),
            'two_stage_enabled' => (bool) config('services.emergency_ai.two_stage_enabled', true),
            'ready' => collect($checks)->contains(fn ($row) => ($row['ok'] ?? false) === true),
        ];
    }

    /** @return list<string> */
    private function providerChain(): array
    {
        $primary = strtolower((string) config('services.emergency_ai.provider', 'gemini'));
        if ($primary === 'ollama' && ! config('services.emergency_ai.ollama_enabled', false)) {
            $primary = 'gemini';
        }
        $failover = config('services.emergency_ai.failover_providers', ['openai']);
        if (! is_array($failover)) {
            $failover = [];
        }

        $chain = [$primary];
        foreach ($failover as $provider) {
            $provider = strtolower(trim((string) $provider));
            if ($provider === 'ollama' && ! config('services.emergency_ai.ollama_enabled', false)) {
                continue;
            }
            if ($provider !== '' && ! in_array($provider, $chain, true) && $this->providerConfigured($provider)) {
                $chain[] = $provider;
            }
        }

        // Keep only providers that are actually configured.
        return array_values(array_filter(
            $chain,
            fn (string $p) => in_array($p, ['ollama', 'gemini', 'openai', 'groq'], true) && $this->providerConfigured($p)
        ));
    }

    private function providerConfigured(string $provider): bool
    {
        return match ($provider) {
            'gemini' => $this->hasUsableApiKey((string) config('services.emergency_ai.gemini_api_key')),
            'openai' => $this->hasUsableApiKey((string) config('services.emergency_ai.openai_api_key')),
            'groq' => $this->hasUsableApiKey((string) config('services.groq.key')),
            'ollama' => filter_var(config('services.emergency_ai.ollama_enabled', false), FILTER_VALIDATE_BOOL),
            default => false,
        };
    }

    private function hasUsableApiKey(string $key): bool
    {
        $key = trim($key);

        return $key !== '' && ! str_contains(strtoupper($key), 'PASTE_YOUR');
    }

    private function analyzeWithProvider(string $provider, string $normalized): array
    {
        return match ($provider) {
            'openai' => $this->analyzeWithOpenAi($normalized),
            'gemini' => $this->analyzeWithGemini($normalized),
            'groq' => $this->analyzeWithGroq($normalized),
            'ollama' => $this->analyzeWithOllama($normalized),
            default => throw new RuntimeException("Unsupported emergency AI provider: {$provider}"),
        };
    }

    /** @return array{ok: bool, detail: string} */
    private function probeProvider(string $provider): array
    {
        try {
            return match ($provider) {
                'gemini' => $this->probeGemini(),
                'openai' => $this->probeOpenAi(),
                'groq' => $this->probeGroq(),
                'ollama' => $this->probeOllama(),
                default => ['ok' => false, 'detail' => 'unknown provider'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, detail: string} */
    private function probeGemini(): array
    {
        $apiKey = config('services.emergency_ai.gemini_api_key');
        if (empty($apiKey)) {
            return ['ok' => false, 'detail' => 'GEMINI_API_KEY missing'];
        }
        $model = config('services.emergency_ai.gemini_model', 'gemini-2.0-flash');
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
        $apiKey = config('services.emergency_ai.openai_api_key');
        if (empty($apiKey)) {
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
    private function probeOllama(): array
    {
        $baseUrl = rtrim((string) config('services.emergency_ai.ollama_base_url'), '/');
        $response = Http::timeout(5)->acceptJson()->get("{$baseUrl}/api/tags");

        return [
            'ok' => $response->successful(),
            'detail' => $response->successful() ? 'reachable' : 'HTTP '.$response->status().' at '.$baseUrl,
        ];
    }

    public function fallbackAnalysis(string $transcript): array
    {
        $normalized = $this->normalizer->normalize($transcript);
        $override = (int) config('services.emergency_ai.rules_override_score', 85);
        $hint = (int) config('services.emergency_ai.rules_hint_score', 60);

        // Ladder 1: strong clinical override
        $rulesResult = $this->clinicalRules->resolve($normalized, $override);
        if ($rulesResult !== null) {
            $rulesResult['ai_source'] = 'fallback+rules';
            $rulesResult['fallback_reason'] = $this->lastFallbackReason ?? 'provider_unavailable';

            return $this->attachRetrievalMeta($this->finalizeAnalysis($normalized, $rulesResult));
        }

        // Ladder 2: softer clinical match (still protocol-backed, not invented)
        $soft = $this->clinicalRules->resolve($normalized, $hint);
        if ($soft !== null) {
            $soft['ai_source'] = 'fallback+rules';
            $soft['ai_confidence'] = 'medium';
            $soft['fallback_reason'] = $this->lastFallbackReason ?? 'provider_unavailable';

            return $this->attachRetrievalMeta($this->finalizeAnalysis($normalized, $soft));
        }

        // Ladder 3: best keyword match even below hint (protocol steps, low confidence)
        $match = $this->clinicalRules->scoreMatch($normalized);
        if ($match !== null && $match['score'] >= 40) {
            $fromMatch = $this->clinicalRules->buildFromMatch($match, 'fallback+rules', 'low');
            $fromMatch['fallback_reason'] = $this->lastFallbackReason ?? 'provider_unavailable';

            return $this->attachRetrievalMeta($this->finalizeAnalysis($normalized, $fromMatch));
        }

        // Ladder 4: explicit unknown protocol (never invent null category / empty steps)
        $unknown = $this->clinicalRules->protocolForCategory('unknown');

        return $this->attachRetrievalMeta($this->finalizeAnalysis($normalized, [
            'detected_category' => 'unknown',
            'severity_level' => $unknown['severity_level'] ?? EmergencySosSignal::SEVERITY_STABLE,
            'suspected_condition' => $unknown['suspected_condition']
                ?? 'حالة طارئة غير مصنّفة بدقة — يُفضّل تقييماً طبياً فورياً',
            'immediate_first_aid_steps' => $unknown['first_aid_steps'] ?? [
                'تأكد من سلامة المكان وابتعد عن أي خطر مباشر.',
                'تحقق من التنفس والنبض واستجابة المصاب.',
                'اتصل بخدمات الطوارئ المحلية فوراً (112 أو 140).',
                'لا تُعطِ الطعام أو الشراب إذا كان المصاب فاقداً للوعي.',
                'ابقَ مع المصاب وراقب تغيّر الحالة حتى وصول الإسعاف.',
            ],
            'warning_signs' => $unknown['warning_signs'] ?? ['تدهور سريع', 'توقف تنفس', 'فقدان وعي'],
            'do_not' => $unknown['do_not'] ?? ['لا تؤجل الاتصال بالطوارئ', 'لا تعطِ ماءً لفاقد الوعي'],
            'ai_confidence' => 'low',
            'ai_source' => 'fallback',
            'rules_score' => $match['score'] ?? null,
            'call_emergency_now' => true,
            'fallback_reason' => $this->lastFallbackReason ?? 'provider_unavailable',
        ]));
    }

    private function attachRetrievalMeta(array $analysis): array
    {
        $analysis['retrieval_mode'] = $this->protocolRetriever->lastRetrievalMode();
        $analysis['matched_protocol'] = $analysis['detected_category'] ?? null;
        $analysis['fallback_reason'] = $analysis['fallback_reason'] ?? $this->lastFallbackReason;
        $analysis['ai_provider'] = $analysis['ai_provider'] ?? $this->lastProviderUsed;

        return $analysis;
    }

    public function postProcessAnalysis(string $transcript, array $analysis): array
    {
        $normalized = $this->normalizer->normalize($transcript);

        return $this->finalizeAnalysis($normalized, $analysis, $analysis['ai_source'] ?? 'ai');
    }

    private function finalizeAnalysis(string $normalizedTranscript, array $analysis, ?string $source = null): array
    {
        if ($source !== null && ! isset($analysis['ai_source'])) {
            $analysis['ai_source'] = $source;
        }

        $analysis = $this->clinicalRules->enrich(
            $normalizedTranscript,
            $analysis,
            (int) config('services.emergency_ai.rules_hint_score', 60)
        );

        $rulesMatch = $this->clinicalRules->scoreMatch($normalizedTranscript);
        $hintThreshold = (int) config('services.emergency_ai.rules_hint_score', 60);

        if ($rulesMatch !== null
            && $rulesMatch['score'] >= $hintThreshold
            && ($analysis['ai_source'] ?? '') === 'fallback') {
            $analysis['ai_source'] = 'fallback+rules';
            $analysis['detected_category'] = $analysis['detected_category'] ?? $rulesMatch['category'];
            $analysis['rules_score'] = $rulesMatch['score'];
        }

        // Never persist a null/invalid category — remap via rules or unknown first.
        $category = $analysis['detected_category'] ?? null;
        if ($category === null
            || $category === ''
            || $this->clinicalRules->protocolForCategory($category) === null) {
            $rescue = $this->clinicalRules->scoreMatch($normalizedTranscript);
            if ($rescue !== null && $rescue['score'] >= 40) {
                $analysis['detected_category'] = $rescue['category'];
                $analysis['rules_score'] = $analysis['rules_score'] ?? $rescue['score'];
            } else {
                $analysis['detected_category'] = 'unknown';
            }
        }

        $category = $analysis['detected_category'] ?? 'unknown';
        $protocol = $this->clinicalRules->protocolForCategory($category);

        if ($protocol !== null) {
            $analysis['warning_signs'] = ! empty($analysis['warning_signs'])
                ? $analysis['warning_signs']
                : ($protocol['warning_signs'] ?? []);
            $analysis['do_not'] = ! empty($analysis['do_not'])
                ? $analysis['do_not']
                : ($protocol['do_not'] ?? []);
            if (empty($analysis['suspected_condition'])) {
                $analysis['suspected_condition'] = $protocol['suspected_condition'] ?? $analysis['suspected_condition'];
            }
            // Fill missing/invalid severity from protocol — never invent "Urgent" blindly.
            if ($this->normalizeSeverity($analysis['severity_level'] ?? null) === null) {
                $analysis['severity_level'] = $protocol['severity_level']
                    ?? EmergencySosSignal::SEVERITY_STABLE;
            }
        } elseif ($this->normalizeSeverity($analysis['severity_level'] ?? null) === null) {
            $analysis['severity_level'] = EmergencySosSignal::SEVERITY_STABLE;
        }

        $steps = $this->stepValidator->sanitize(
            $analysis['immediate_first_aid_steps'] ?? [],
            $category
        );

        if ($steps === [] && ! empty($protocol['first_aid_steps'])) {
            $steps = $protocol['first_aid_steps'];
        }

        $analysis['immediate_first_aid_steps'] = $steps;
        $analysis['call_emergency_now'] = ($analysis['severity_level'] ?? '') === EmergencySosSignal::SEVERITY_CRITICAL
            || (bool) ($analysis['call_emergency_now'] ?? false);

        return $this->normalizeAiPayload($analysis);
    }

    private function analyzeWithOllama(string $transcript): array
    {
        if (config('services.emergency_ai.two_stage_enabled', true)) {
            return $this->twoStageAnalysis($transcript, fn (array $messages) => $this->ollamaChat($messages));
        }

        return $this->singleStageAnalysis($transcript, fn (string $system, string $user) => $this->ollamaChat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]));
    }

    private function analyzeWithGemini(string $transcript): array
    {
        $apiKey = trim((string) config('services.emergency_ai.gemini_api_key'));
        $model = config('services.emergency_ai.gemini_model', 'gemini-2.0-flash');

        if ($apiKey === '' || str_contains(strtoupper($apiKey), 'PASTE_YOUR')) {
            throw new RuntimeException('GEMINI_API_KEY is missing. Paste your key in .env then run: php artisan config:clear');
        }

        if (config('services.emergency_ai.two_stage_enabled', true)) {
            return $this->twoStageAnalysis($transcript, function (array $messages) use ($apiKey, $model) {
                $system = '';
                $userChunks = [];
                foreach ($messages as $message) {
                    if (($message['role'] ?? '') === 'system') {
                        $system .= trim((string) $message['content'])."\n";
                    } else {
                        $userChunks[] = trim((string) $message['content']);
                    }
                }

                $prompt = trim($system) !== ''
                    ? "SYSTEM INSTRUCTIONS:\n".trim($system)."\n\nUSER:\n".implode("\n\n", $userChunks)
                    : implode("\n\n", $userChunks);

                return $this->decodeJsonPayload($this->geminiGenerate($apiKey, $model, $prompt));
            });
        }

        $payload = $this->singleStageAnalysis($transcript, function (string $system, string $user) use ($apiKey, $model) {
            return $this->decodeJsonPayload($this->geminiGenerate($apiKey, $model, $system."\n\n".$user));
        });

        return $payload;
    }

    private function analyzeWithOpenAi(string $transcript): array
    {
        $apiKey = config('services.emergency_ai.openai_api_key');
        $model = config('services.emergency_ai.openai_model', 'gpt-4o-mini');

        if (empty($apiKey)) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        if (config('services.emergency_ai.two_stage_enabled', true)) {
            return $this->twoStageAnalysis($transcript, fn (array $messages) => $this->openAiChat($apiKey, $model, $messages));
        }

        return $this->singleStageAnalysis($transcript, fn (string $system, string $user) => $this->openAiChat($apiKey, $model, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]));
    }

    private function analyzeWithGroq(string $transcript): array
    {
        if (! $this->hasUsableApiKey(trim((string) config('services.groq.key')))) {
            throw new RuntimeException('GROQ_API_KEY is not configured.');
        }

        if (config('services.emergency_ai.two_stage_enabled', true)) {
            return $this->twoStageAnalysis(
                $transcript,
                fn (array $messages) => $this->groqChat($messages)
            );
        }

        return $this->singleStageAnalysis($transcript, fn (string $system, string $user) => $this->groqChat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]));
    }

    private function twoStageAnalysis(string $transcript, callable $chatFn): array
    {
        $classification = $chatFn([
            ['role' => 'system', 'content' => $this->buildClassificationPrompt()],
            ['role' => 'user', 'content' => $this->buildClassificationUserPrompt($transcript)],
        ]);

        $category = is_string($classification['detected_category'] ?? null)
            ? $classification['detected_category']
            : null;
        $protocol = $this->clinicalRules->protocolForCategory($category);

        // Rescue invalid or vague "unknown" stage-1 with clinical match before advisory.
        $needsRescue = $protocol === null || $category === 'unknown';
        if ($needsRescue) {
            $match = $this->clinicalRules->scoreMatch($transcript);
            if ($match !== null && $match['score'] >= 40) {
                $category = $match['category'];
                $classification['detected_category'] = $category;
                $classification['confidence'] = $classification['confidence'] ?? 'medium';
                $protocol = $match['protocol'];
            } elseif ($protocol === null) {
                $category = 'unknown';
                $classification['detected_category'] = 'unknown';
                $protocol = $this->clinicalRules->protocolForCategory('unknown');
            }
        }

        // Strong keyword conflict: prefer protocol category over weak LLM guess.
        $conflictScore = (int) config('services.emergency_ai.category_conflict_score', 70);
        $rulesMatch = $this->clinicalRules->scoreMatch($transcript);
        if ($rulesMatch !== null
            && $rulesMatch['score'] >= $conflictScore
            && $category !== null
            && $category !== 'unknown'
            && $rulesMatch['category'] !== $category) {
            $category = $rulesMatch['category'];
            $classification['detected_category'] = $category;
            $classification['confidence'] = 'medium';
            $classification['rules_score'] = $rulesMatch['score'];
            $classification['category_conflict_resolved'] = true;
            $protocol = $rulesMatch['protocol'];
        }

        $advisory = $chatFn([
            ['role' => 'system', 'content' => $this->buildAdvisoryPrompt($protocol)],
            ['role' => 'user', 'content' => $this->buildAdvisoryUserPrompt($transcript, $classification)],
        ]);

        // Prefer classification category; keep advisory clinical text.
        $merged = array_merge($classification, $advisory, [
            'detected_category' => $category,
            'ai_confidence' => $classification['confidence'] ?? $advisory['confidence'] ?? 'medium',
            'ai_source' => 'ai_two_stage',
            'rules_score' => $classification['rules_score'] ?? $rulesMatch['score'] ?? null,
        ]);

        return $merged;
    }

    private function singleStageAnalysis(string $transcript, callable $chatFn): array
    {
        $payload = $chatFn($this->buildSystemPrompt(), $this->buildUserPrompt($transcript));

        return array_merge($payload, [
            'ai_confidence' => $payload['confidence'] ?? 'medium',
            'ai_source' => 'ai',
        ]);
    }

    /** @param list<array{role: string, content: string}> $messages */
    private function ollamaChat(array $messages): array
    {
        $baseUrl = rtrim((string) config('services.emergency_ai.ollama_base_url'), '/');
        $model = config('services.emergency_ai.ollama_model', 'qwen2.5:7b');
        $timeout = (int) config('services.emergency_ai.ollama_timeout', 90);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post("{$baseUrl}/api/chat", [
                'model' => $model,
                'stream' => false,
                'format' => 'json',
                'messages' => $messages,
                'options' => [
                    'temperature' => 0.2,
                    'num_predict' => 1536,
                ],
            ]);

        $response->throw();

        $jsonText = data_get($response->json(), 'message.content');

        if (! is_string($jsonText) || trim($jsonText) === '') {
            throw new RuntimeException('Ollama returned an empty triage payload.');
        }

        return $this->decodeJsonPayload($jsonText);
    }

    private function geminiGenerate(string $apiKey, string $model, string $text): string
    {
        $response = Http::timeout(self::CLOUD_TIMEOUT_SECONDS)
            ->acceptJson()
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $text]]]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        $response->throw();

        $jsonText = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($jsonText) || trim($jsonText) === '') {
            throw new RuntimeException('Gemini returned an empty triage payload.');
        }

        return $jsonText;
    }

    /** @param list<array{role: string, content: string}> $messages */
    private function openAiChat(string $apiKey, string $model, array $messages): array
    {
        $response = Http::timeout(self::CLOUD_TIMEOUT_SECONDS)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ]);

        $response->throw();

        $jsonText = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($jsonText) || trim($jsonText) === '') {
            throw new RuntimeException('OpenAI returned an empty triage payload.');
        }

        return $this->decodeJsonPayload($jsonText);
    }

    /** @param list<array{role: string, content: string}> $messages */
    private function groqChat(array $messages): array
    {
        $content = app(GroqChatClient::class)->complete($messages, self::CLOUD_TIMEOUT_SECONDS);

        return $this->decodeJsonPayload($content);
    }

    private function buildClassificationPrompt(): string
    {
        return <<<'PROMPT'
أنت خبير فرز طوارئ. حلّل وصف الحالة وصنّفها فقط.
لا تخمّن تشخيصاً طبياً دقيقاً. إذا لم تتضح الفئة استخدم "unknown".
فضّل الفئات المعرفة فقط عندما تدعم الأعراض ذلك بقوة.
إذا وُجدت مرشّحات سريرية في رسالة المستخدم، اختر منها إن ناسبت الوصف؛ وإلا استخدم unknown.
أجب بـ JSON صالح فقط:
{
  "detected_category": "choking|cardiac|stroke|bleeding|unconscious|seizure|anaphylaxis|burns|fracture|poisoning|drowning|minor_injury|unknown",
  "detected_symptoms": ["عرض 1", "عرض 2"],
  "severity_level": "Critical|Urgent|Stable",
  "confidence": "high|medium|low"
}
PROMPT;
    }

    private function buildClassificationUserPrompt(string $transcript): string
    {
        $candidates = $this->protocolRetriever->formatForPrompt($transcript, 3);
        $context = $this->patientContextBlock
            ? "\n\n{$this->patientContextBlock}\n"
            : "\n";

        return "وصف الحالة:\n{$transcript}{$context}\n{$candidates}\n\nصنّف الحالة وأرجع JSON فقط.";
    }

    private function buildUserPrompt(string $transcript): string
    {
        $candidates = $this->protocolRetriever->formatForPrompt($transcript, 3);
        $context = $this->patientContextBlock
            ? "\n\n{$this->patientContextBlock}\n"
            : "\n";

        return "وصف الحالة:\n{$transcript}{$context}\n{$candidates}\n\nحلّل وارجع JSON فقط.";
    }

    private function buildAdvisoryPrompt(?array $protocol): string
    {
        $protocolHint = $protocol
            ? 'التزم بالبروتوكول المرجعي التالي وأعد صياغته عملياً دون اختراع خطوات خطرة: '.json_encode([
                'category' => $protocol['category'] ?? null,
                'condition' => $protocol['suspected_condition'] ?? '',
                'severity_level' => $protocol['severity_level'] ?? null,
                'first_aid_steps' => $protocol['first_aid_steps'] ?? [],
                'warning_signs' => $protocol['warning_signs'] ?? [],
                'do_not' => $protocol['do_not'] ?? [],
            ], JSON_UNESCAPED_UNICODE)
            : 'لا يوجد بروتوكول محدد — قدم إرشادات آمنة عامة ومخصصة للوصف فقط.';

        return <<<PROMPT
أنت استشاري طوارئ. اكتب إرشادات إسعاف أولي عملية بالعربية.
{$protocolHint}
لا تخالف قسم do_not. فضّل خطوات البروتوكول وعدّل الصياغة حسب وصف الحالة فقط.

أجب بـ JSON فقط:
{
  "severity_level": "Critical|Urgent|Stable",
  "suspected_condition": "يُشتبه بـ ...",
  "immediate_first_aid_steps": ["5-7 خطوات محددة"],
  "warning_signs": ["علامات خطرة"],
  "do_not": ["ممنوعات واضحة"]
}
PROMPT;
    }

    private function buildAdvisoryUserPrompt(string $transcript, array $classification): string
    {
        $summary = json_encode($classification, JSON_UNESCAPED_UNICODE);
        $context = $this->patientContextBlock
            ? "\n\n{$this->patientContextBlock}\n"
            : "\n";

        return "وصف الحالة:\n{$transcript}{$context}\nنتيجة التصنيف:\n{$summary}\n\nاكتب الإرشادات النهائية JSON فقط.";
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
أنت استشاري طوارئ. حلّل الحالة وقدّم فرزاً آمناً.
أجب JSON فقط:
{
  "detected_category": "choking|cardiac|stroke|...|unknown",
  "severity_level": "Critical|Urgent|Stable",
  "suspected_condition": "يُشتبه بـ ...",
  "immediate_first_aid_steps": ["5-7 خطوات"],
  "warning_signs": ["..."],
  "do_not": ["..."],
  "confidence": "high|medium|low"
}
PROMPT;
    }

    private function decodeJsonPayload(string $jsonText): array
    {
        $jsonText = trim($jsonText);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $jsonText, $matches)) {
            $jsonText = trim($matches[1]);
        }

        $payload = json_decode($jsonText, true);

        if (! is_array($payload)) {
            throw new RuntimeException('AI triage payload is not valid JSON.');
        }

        return $payload;
    }

    private function normalizeAiPayload(array $payload): array
    {
        $severity = $this->normalizeSeverity($payload['severity_level'] ?? null);
        $condition = trim((string) ($payload['suspected_condition'] ?? ''));
        $steps = $payload['immediate_first_aid_steps'] ?? null;

        if ($severity === null) {
            throw new RuntimeException('AI returned an invalid severity_level value.');
        }

        if ($condition === '') {
            throw new RuntimeException('AI returned an empty suspected_condition value.');
        }

        if (! is_array($steps) || count($steps) < 1) {
            throw new RuntimeException('AI returned invalid immediate_first_aid_steps.');
        }

        $normalizedSteps = array_values(array_filter(array_map(
            static fn ($step) => trim((string) $step),
            $steps
        )));

        if ($normalizedSteps === []) {
            throw new RuntimeException('AI returned empty first aid steps after normalization.');
        }

        return [
            'detected_category' => $payload['detected_category'] ?? null,
            'severity_level' => $severity,
            'suspected_condition' => $condition,
            'immediate_first_aid_steps' => $normalizedSteps,
            'warning_signs' => array_values(array_filter((array) ($payload['warning_signs'] ?? []))),
            'do_not' => array_values(array_filter((array) ($payload['do_not'] ?? []))),
            'ai_confidence' => $payload['ai_confidence'] ?? $payload['confidence'] ?? 'medium',
            'ai_source' => $payload['ai_source'] ?? 'ai',
            'rules_score' => $payload['rules_score'] ?? null,
            'call_emergency_now' => (bool) ($payload['call_emergency_now'] ?? $severity === EmergencySosSignal::SEVERITY_CRITICAL),
            'fallback_reason' => $payload['fallback_reason'] ?? null,
            'retrieval_mode' => $payload['retrieval_mode'] ?? null,
            'matched_protocol' => $payload['matched_protocol'] ?? ($payload['detected_category'] ?? null),
            'ai_provider' => $payload['ai_provider'] ?? null,
            'patient_context_used' => (bool) ($payload['patient_context_used'] ?? false),
        ];
    }

    private function normalizeSeverity(mixed $severity): ?string
    {
        if (! is_string($severity)) {
            return null;
        }

        $map = [
            'critical' => EmergencySosSignal::SEVERITY_CRITICAL,
            'urgent' => EmergencySosSignal::SEVERITY_URGENT,
            'stable' => EmergencySosSignal::SEVERITY_STABLE,
            'حرج' => EmergencySosSignal::SEVERITY_CRITICAL,
            'عاجل' => EmergencySosSignal::SEVERITY_URGENT,
            'مستقر' => EmergencySosSignal::SEVERITY_STABLE,
        ];

        $normalized = $map[strtolower(trim($severity))] ?? trim($severity);

        return in_array($normalized, self::ALLOWED_SEVERITIES, true) ? $normalized : null;
    }

    public function logFailure(\Throwable $exception, string $transcript, array $context = []): void
    {
        Log::error('Emergency AI triage analysis failed', array_merge([
            'exception' => $exception->getMessage(),
            'exception_class' => $exception::class,
            'transcript_excerpt' => mb_substr($transcript, 0, 300),
            'is_timeout' => $exception instanceof ConnectionException,
            'is_http_error' => $exception instanceof RequestException,
            'provider' => config('services.emergency_ai.provider'),
        ], $context));
    }

    public function logAnalytics(array $analysis, string $transcript, bool $usedFallback): void
    {
        if (! config('services.emergency_ai.analytics_enabled', true)) {
            return;
        }

        Log::info('Emergency triage analytics', [
            'severity' => $analysis['severity_level'] ?? null,
            'category' => $analysis['detected_category'] ?? null,
            'source' => $analysis['ai_source'] ?? null,
            'confidence' => $analysis['ai_confidence'] ?? null,
            'rules_score' => $analysis['rules_score'] ?? null,
            'fallback' => $usedFallback,
            'fallback_reason' => $analysis['fallback_reason'] ?? $this->lastFallbackReason,
            'provider' => $analysis['ai_provider'] ?? config('services.emergency_ai.provider'),
            'model' => $this->modelLabelForProvider((string) ($analysis['ai_provider'] ?? config('services.emergency_ai.provider'))),
            'retrieval_mode' => $analysis['retrieval_mode'] ?? $this->protocolRetriever->lastRetrievalMode(),
            'patient_context_used' => (bool) ($analysis['patient_context_used'] ?? false),
            'transcript_length' => mb_strlen($transcript),
        ]);
    }

    private function modelLabelForProvider(string $provider): ?string
    {
        return match ($provider) {
            'gemini' => (string) config('services.emergency_ai.gemini_model'),
            'openai' => (string) config('services.emergency_ai.openai_model'),
            'ollama' => (string) config('services.emergency_ai.ollama_model'),
            default => null,
        };
    }
}
