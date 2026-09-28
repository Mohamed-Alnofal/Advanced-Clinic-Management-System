<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SearchExtendedApiTest extends TestCase
{
    public function test_governorates_endpoint_is_public(): void
    {
        $this->createGovernorate('Damascus');

        $this->getJson('/api/governorates')
            ->assertOk();
    }

    public function test_authenticated_user_can_search_by_governorate(): void
    {
        $governorate = $this->createGovernorate('Aleppo');
        $setup = $this->createDoctorUser();

        $this->authenticateAs($this->createPatient())
            ->postJson('/api/search-by-governorate', [
                'governorate_id' => $governorate->id,
            ])
            ->assertOk();
    }

    public function test_search_by_governorate_requires_authentication(): void
    {
        $this->postJson('/api/search-by-governorate', [
            'governorate_id' => 1,
        ])->assertUnauthorized();
    }
}
