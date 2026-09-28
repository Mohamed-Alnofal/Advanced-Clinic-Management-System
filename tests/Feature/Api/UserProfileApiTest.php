<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class UserProfileApiTest extends TestCase
{
    public function test_authenticated_user_can_view_profile(): void
    {
        $patient = $this->createPatient(['name' => 'Profile Patient']);

        $this->authenticateAs($patient)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('name', 'Profile Patient');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_guest_can_view_doctor_public_profile(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();

        $this->getJson('/api/doctors/'.$setup['doctor']->id)
            ->assertOk();
    }

    public function test_show_doctor_returns_not_found_for_invalid_id(): void
    {
        $this->getJson('/api/doctors/99999')->assertNotFound();
    }
}
