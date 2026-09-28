<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Jobs\SummarizeEmergencyTriageJob;
use Modules\Core\Models\EmergencySosSignal;
use Modules\Core\Services\EmergencyAiAnalyzerService;
use Modules\Core\Services\EmergencyClinicalRulesService;
use Tests\TestCase;

/**
 * Pillar 3 — Emergency SOS hybrid AI + clinical rules + async queue.
 */
class EmergencyHybridAiFlowTest extends TestCase
{
    public function test_clinical_rules_override_skips_external_ai_when_score_is_high(): void
    {
        config([
            'services.emergency_ai.provider' => 'gemini',
            'services.emergency_ai.gemini_api_key' => 'test-key',
            'services.emergency_ai.rules_override_score' => 85,
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake();

        $response = $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'ألم شديد في الصدر وضيق تنفس وتعرق غزير منذ دقائق',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ai_source', 'clinical_rules')
            ->assertJsonPath('data.call_emergency_now', true);

        $this->assertGreaterThanOrEqual(85, (int) $response->json('data.rules_score'));
        Http::assertNothingSent();
    }

    public function test_ai_provider_is_called_when_rules_score_is_below_threshold(): void
    {
        $payload = json_encode([
            'detected_category' => 'general',
            'severity_level' => 'Stable',
            'suspected_condition' => 'تعب عام خفيف',
            'immediate_first_aid_steps' => ['ارتاح وراقب الأعراض'],
            'warning_signs' => ['تدهور مفاجئ'],
            'do_not' => ['لا تتجاهل تفاقم الأعراض'],
            'confidence' => 'medium',
        ], JSON_UNESCAPED_UNICODE);

        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.ollama_base_url' => 'http://127.0.0.1:11434',
            'services.emergency_ai.ollama_enabled' => true,
            'services.emergency_ai.rules_override_score' => 95,
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => false,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake([
            '*' => Http::response([
                'message' => ['content' => $payload],
            ], 200),
        ]);

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.51,
            'longitude' => 36.27,
            'text_transcript' => 'أشعر بتعب خفيف فقط بدون أعراض أخرى واضحة',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request) => true);
        $this->assertGreaterThanOrEqual(1, count(Http::recorded()));
    }

    public function test_summarize_emergency_triage_job_is_queued(): void
    {
        Queue::fake();

        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_override_score' => 85,
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake();

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'اختناق حاد وصعوبة شديدة في التنفس',
        ])->assertOk();

        Queue::assertPushed(SummarizeEmergencyTriageJob::class, function (SummarizeEmergencyTriageJob $job) {
            return $job->signalId > 0 && $job->transcript !== '';
        });
    }

    public function test_clinical_rules_service_returns_null_below_override_threshold(): void
    {
        /** @var EmergencyClinicalRulesService $rules */
        $rules = app(EmergencyClinicalRulesService::class);

        $result = $rules->resolve('تعب بسيط فقط', 85);

        $this->assertNull($result);
    }

    public function test_ai_analyzer_uses_mocked_http_for_gemini_without_paid_calls(): void
    {
        $payload = json_encode([
            'detected_category' => 'neurological',
            'severity_level' => 'Urgent',
            'suspected_condition' => 'صداع حاد يحتاج تقييماً',
            'immediate_first_aid_steps' => ['أرح المريض في غرفة هادئة'],
            'warning_signs' => ['قيء متكرر'],
            'do_not' => ['لا تعطِ مسكنات قوية دون استشارة'],
            'confidence' => 'medium',
        ], JSON_UNESCAPED_UNICODE);

        config([
            'services.emergency_ai.provider' => 'gemini',
            'services.emergency_ai.gemini_api_key' => 'fake-gemini-key',
            'services.emergency_ai.rules_override_score' => 99,
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => false,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $payload]]]]],
            ], 200),
        ]);

        /** @var EmergencyAiAnalyzerService $analyzer */
        $analyzer = app(EmergencyAiAnalyzerService::class);
        $analysis = $analyzer->analyze('صداع خفيف منذ ساعة بدون أعراض أخرى');

        $this->assertArrayHasKey('severity_level', $analysis);
        $this->assertContains($analysis['severity_level'], [
            EmergencySosSignal::SEVERITY_CRITICAL,
            EmergencySosSignal::SEVERITY_URGENT,
            EmergencySosSignal::SEVERITY_STABLE,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com');
        });
    }

    public function test_protocol_retriever_candidates_are_injected_into_ai_prompt(): void
    {
        $payload = json_encode([
            'detected_category' => 'cardiac',
            'severity_level' => 'Critical',
            'suspected_condition' => 'يُشتبه بأزمة قلبية',
            'immediate_first_aid_steps' => ['اتصل بالطوارئ'],
            'warning_signs' => ['ألم صدر'],
            'do_not' => ['لا تمشي'],
            'confidence' => 'high',
        ], JSON_UNESCAPED_UNICODE);

        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.ollama_base_url' => 'http://127.0.0.1:11434',
            'services.emergency_ai.ollama_enabled' => true,
            'services.emergency_ai.rules_override_score' => 999,
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => false,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake([
            '*' => Http::response([
                'message' => ['content' => $payload],
            ], 200),
        ]);

        /** @var EmergencyAiAnalyzerService $analyzer */
        $analyzer = app(EmergencyAiAnalyzerService::class);
        $analyzer->analyze('ضغط خفيف على الصدر بدون تعرق');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $messages = $body['messages'] ?? [];
            $userContent = collect($messages)
                ->firstWhere('role', 'user')['content'] ?? '';

            return str_contains((string) $userContent, 'مرشّحات سريرية')
                || str_contains((string) $userContent, 'cardiac');
        });
    }
}
