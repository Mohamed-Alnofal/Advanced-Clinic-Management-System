<?php

namespace Tests\Feature\Api;

use Modules\Users\Models\FcmToken;
use Tests\TestCase;

class FcmApiTest extends TestCase
{
    public function test_authenticated_user_can_store_fcm_token(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/fcm/store', [
                'token' => 'fcm-device-token-abc123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Token saved successfully');

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $patient->id,
            'token' => 'fcm-device-token-abc123',
        ]);
    }

    public function test_fcm_store_requires_authentication(): void
    {
        $this->postJson('/api/fcm/store', ['token' => 'test'])
            ->assertUnauthorized();
    }

    public function test_fcm_store_validates_token(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/fcm/store', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }
}
