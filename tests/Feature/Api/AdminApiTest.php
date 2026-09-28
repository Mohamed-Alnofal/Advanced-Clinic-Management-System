<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AdminApiTest extends TestCase
{
    public function test_admin_can_list_users(): void
    {
        $admin = $this->createAdmin();
        $this->createPatient();
        $this->createDoctorUser();

        $this->authenticateAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_admin_users_requires_authentication(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_admin_can_approve_unverified_doctor(): void
    {
        $admin = $this->createAdmin();
        $doctorSetup = $this->createDoctorUser(
            ['is_verified' => false, 'status' => 'active'],
            ['is_verified' => false]
        );

        $this->authenticateAs($admin)
            ->postJson('/api/admin/verification/doctors/'.$doctorSetup['doctor']->id.'/approve')
            ->assertOk()
            ->assertJsonPath('message', 'Doctor approved successfully');

        $this->assertTrue((bool) $doctorSetup['doctor']->fresh()->is_verified);
        $this->assertSame('accepted', $doctorSetup['doctorUser']->fresh()->status);
    }

    public function test_patient_cannot_approve_doctors(): void
    {
        $patient = $this->createPatient();
        $doctorSetup = $this->createDoctorUser([], ['is_verified' => false]);

        $this->authenticateAs($patient)
            ->postJson('/api/admin/verification/doctors/'.$doctorSetup['doctor']->id.'/approve')
            ->assertForbidden();
    }
}
