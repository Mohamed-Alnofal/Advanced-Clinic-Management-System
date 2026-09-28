<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Modules\Core\Models\EmergencySosSignal;
use Tests\TestCase;

class EmergencyTriageApiTest extends TestCase
{
    protected function fakeAiTriageResponse(string $provider = 'ollama'): void
    {
        $payload = json_encode([
            'detected_category' => 'cardiac',
            'severity_level' => 'Critical',
            'suspected_condition' => 'يُشتبه بأزمة قلبية حادة',
            'immediate_first_aid_steps' => [
                'اتصل بالطوارئ فوراً',
                'أبقِ المريض في وضعية جلوس مريحة',
                'راقب التنفس والنبض',
            ],
            'warning_signs' => ['ألم صدر', 'ضيق تنفس'],
            'do_not' => ['لا تدعه يمشي'],
            'confidence' => 'high',
        ], JSON_UNESCAPED_UNICODE);

        config([
            'services.emergency_ai.provider' => $provider,
            'services.emergency_ai.ollama_base_url' => 'http://127.0.0.1:11434',
            'services.emergency_ai.ollama_enabled' => true,
            'services.emergency_ai.two_stage_enabled' => false,
        ]);

        if ($provider === 'gemini') {
            config(['services.emergency_ai.gemini_api_key' => 'test-gemini-key']);
            Http::fake([
                'generativelanguage.googleapis.com/*' => Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => $payload]]]]],
                ], 200),
            ]);

            return;
        }

        Http::fake([
            '*' => Http::response([
                'message' => ['content' => $payload],
            ], 200),
        ]);
    }

    public function test_guest_can_submit_emergency_triage_via_clinical_rules(): void
    {
        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake();

        $response = $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'أشعر بألم شديد في الصدر وضيق في التنفس منذ 10 دقائق',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.severity_level', 'Critical')
            ->assertJsonPath('data.severity', 'CRITICAL')
            ->assertJsonPath('data.detected_category', 'cardiac')
            ->assertJsonPath('data.ai_source', 'clinical_rules')
            ->assertJsonPath('data.ai_confidence', 'high')
            ->assertJsonPath('data.call_emergency_now', true)
            ->assertJsonPath('data.suspected_condition', 'يُشتبه بأزمة قلبية محتملة')
            ->assertJsonPath('data.ai_fallback_used', false)
            ->assertJsonStructure([
                'data' => [
                    'warning_signs',
                    'do_not',
                    'detected_category',
                    'ai_confidence',
                    'ai_source',
                    'call_emergency_now',
                    'rules_score',
                ],
            ]);

        Http::assertNothingSent();
    }

    public function test_guest_can_submit_emergency_triage_via_ai(): void
    {
        $this->fakeAiTriageResponse();

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'أشعر بضعف عام ودوخة بدون أعراض محددة',
        ])
            ->assertOk()
            ->assertJsonPath('data.ai_source', 'ai')
            ->assertJsonPath('data.suspected_condition', 'يُشتبه بأزمة قلبية حادة');
    }

    public function test_authenticated_patient_links_patient_id(): void
    {
        $this->fakeAiTriageResponse();

        $patientUser = $this->createPatient();
        $patientRecord = $this->createPatientRecord($patientUser);

        $this->authenticateAs($patientUser)
            ->postJson('/api/v1/emergency/triage', [
                'latitude' => 33.5138,
                'longitude' => 36.2765,
                'text_transcript' => 'أشعر بضعف عام ودوخة بدون أعراض محددة',
            ])
            ->assertOk()
            ->assertJsonPath('data.patient_id', $patientRecord->id);

        $this->assertDatabaseHas('emergency_sos_signals', [
            'patient_id' => $patientRecord->id,
        ]);
    }

    public function test_triage_validates_required_fields(): void
    {
        $this->postJson('/api/v1/emergency/triage', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
                'text_transcript',
            ]);
    }

    public function test_triage_uses_fallback_when_ai_fails(): void
    {
        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.ollama_base_url' => 'http://127.0.0.1:11434',
            'services.emergency_ai.two_stage_enabled' => false,
        ]);

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'أشعر بضعف عام ولا أعرف ما الحالة بالضبط',
        ])
            ->assertOk()
            ->assertJsonPath('data.ai_fallback_used', true)
            ->assertJsonPath('data.severity_level', EmergencySosSignal::SEVERITY_URGENT)
            ->assertJsonPath('data.detected_category', 'unknown')
            ->assertJsonPath('data.ai_source', 'fallback')
            ->assertJsonPath('data.call_emergency_now', true);
    }

    public function test_triage_rules_handle_minor_injury_without_ai(): void
    {
        config([
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);
        Http::fake();

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'إصابة بسيطة في اليد مع نزيف خفيف',
        ])
            ->assertOk()
            ->assertJsonPath('data.ai_fallback_used', false)
            ->assertJsonPath('data.severity_level', EmergencySosSignal::SEVERITY_STABLE)
            ->assertJsonPath('data.ai_source', 'clinical_rules')
            ->assertJsonPath('data.detected_category', 'minor_injury');

        Http::assertNothingSent();
    }

    public function test_triage_fallback_uses_heuristics_for_known_symptoms(): void
    {
        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_override_score' => 999,
        ]);

        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'إصابة بسيطة في اليد مع نزيف خفيف',
        ])
            ->assertOk()
            ->assertJsonPath('data.ai_fallback_used', true)
            ->assertJsonPath('data.severity_level', EmergencySosSignal::SEVERITY_STABLE)
            ->assertJsonPath('data.ai_source', 'fallback+rules')
            ->assertJsonPath('data.detected_category', 'minor_injury');
    }

    public function test_choking_case_uses_clinical_protocol(): void
    {
        config([
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);
        Http::fake();

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'اعاني من حالة اختناق عالي',
        ])
            ->assertOk()
            ->assertJsonPath('data.detected_category', 'choking')
            ->assertJsonPath('data.severity_level', 'Critical')
            ->assertJsonPath('data.ai_source', 'clinical_rules');

        Http::assertNothingSent();
    }

    public function test_guest_critical_triage_does_not_create_ambulance_request(): void
    {
        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake();

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'أشعر بألم شديد في الصدر وضيق في التنفس منذ 10 دقائق',
        ])
            ->assertOk()
            ->assertJsonPath('data.severity_level', 'Critical')
            ->assertJsonPath('data.emergency_request_id', null);
    }

    public function test_authenticated_critical_triage_does_not_auto_dispatch_ambulance(): void
    {
        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake();

        $patientUser = $this->createPatient();
        $this->createPatientRecord($patientUser);

        $this->authenticateAs($patientUser)
            ->postJson('/api/v1/emergency/triage', [
                'latitude' => 33.5138,
                'longitude' => 36.2765,
                'text_transcript' => 'أشعر بألم شديد في الصدر وضيق في التنفس منذ 10 دقائق',
            ])
            ->assertOk()
            ->assertJsonPath('data.severity_level', 'Critical')
            ->assertJsonPath('data.emergency_request_id', null);
    }

    public function test_authenticated_patient_can_request_ambulance_directly(): void
    {
        $patientUser = $this->createPatient();
        $this->createPatientRecord($patientUser);

        $response = $this->authenticateAs($patientUser)
            ->postJson('/api/ambulance/sos', [
                'latitude' => 33.5138,
                'longitude' => 36.2765,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('emergency_requests', [
            'user_id' => $patientUser->id,
            'status' => 'pending',
        ]);
    }

    public function test_dialect_synonym_triage_resolves_cardiac_protocol(): void
    {
        config([
            'services.emergency_ai.provider' => 'ollama',
            'services.emergency_ai.two_stage_enabled' => false,
            'services.emergency_ai.rules_first_enabled' => true,
            'services.emergency_ai.embeddings_enabled' => false,
        ]);

        Http::fake();

        $this->postJson('/api/v1/emergency/triage', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'text_transcript' => 'قلبي عم يوجعني وتعرق بارد من دقايق',
        ])
            ->assertOk()
            ->assertJsonPath('data.detected_category', 'cardiac')
            ->assertJsonPath('data.ai_source', 'clinical_rules');
    }
}
