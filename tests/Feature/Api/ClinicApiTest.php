<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ClinicApiTest extends TestCase
{
    public function test_doctor_can_create_clinic(): void
    {
        $setup = $this->createDoctorUser();

        $response = $this->authenticateAs($setup['doctorUser'])->postJson('/api/clinics', [
            'name' => 'Heart Care Clinic',
            'address' => 'Damascus Street 1',
            'governorate_id' => $setup['governorate']->id,
            'type' => 'clinic',
            'phone' => '0111234567',
            'latitude' => 33.5,
            'longitude' => 36.3,
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['clinic' => ['id', 'medical_center']]);
        $this->assertJsonMessageContains($response, 'Clinic created successfully');

        $this->assertDatabaseHas('clinics', [
            'doctor_id' => $setup['doctor']->id,
        ]);
    }

    public function test_create_clinic_requires_authentication(): void
    {
        $this->postJson('/api/clinics', [])
            ->assertUnauthorized();
    }

    public function test_create_clinic_validates_required_fields(): void
    {
        $setup = $this->createDoctorUser();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/clinics', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'address', 'governorate_id', 'type']);
    }

    public function test_doctor_can_list_own_clinics(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();

        $this->authenticateAs($setup['doctorUser'])
            ->getJson('/api/my-clinics')
            ->assertOk()
            ->assertJsonPath('message', 'Doctor clinics retrieved successfully')
            ->assertJsonStructure(['data']);
    }
}
