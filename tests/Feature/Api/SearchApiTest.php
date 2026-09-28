<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SearchApiTest extends TestCase
{
    public function test_specializations_endpoint_is_public(): void
    {
        $this->createSpecialization('Dermatology');
        $this->createSpecialization('Neurology');

        $this->getJson('/api/specializations')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_specializations_endpoint_hides_duplicate_names(): void
    {
        $this->createSpecialization('Cardiology');
        $this->createSpecialization('Cardiology');
        $this->createSpecialization('Dentistry');

        $this->getJson('/api/specializations')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_search_doctors_by_name(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'user' => ['name' => 'Dr Searchable Unique'],
        ]);
        $this->giveDoctorActiveSubscription($setup['doctor']);
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/search-by-name', ['name' => 'Searchable'])
            ->assertOk()
            ->assertJsonFragment(['id' => $setup['doctor']->id]);
    }

    public function test_unsubscribed_doctor_is_hidden_from_patient_search(): void
    {
        $hidden = $this->createDoctorWithClinicAndSchedule([
            'user' => ['name' => 'Dr Hidden Unique'],
        ]);
        $visible = $this->createDoctorWithClinicAndSchedule([
            'user' => ['name' => 'Dr Visible Unique'],
        ]);
        $this->giveDoctorActiveSubscription($visible['doctor']);
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/search-by-name', ['name' => 'Hidden Unique'])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->authenticateAs($patient)
            ->postJson('/api/search-by-name', ['name' => 'Visible Unique'])
            ->assertOk()
            ->assertJsonFragment(['id' => $visible['doctor']->id]);
    }

    public function test_unverified_subscribed_doctor_is_hidden_from_patient_search(): void
    {
        $setup = $this->createDoctorUser(
            ['name' => 'Dr Unverified Unique'],
            ['is_verified' => false]
        );
        $this->giveDoctorActiveSubscription($setup['doctor']);
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/search-by-name', ['name' => 'Unverified Unique'])
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_by_name_requires_authentication(): void
    {
        $this->postJson('/api/search-by-name', ['name' => 'test'])
            ->assertUnauthorized();
    }
}
