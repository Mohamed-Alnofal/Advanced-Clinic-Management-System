<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class DisputesApiTest extends TestCase
{
    public function test_patient_can_create_dispute_against_doctor(): void
    {
        $patient = $this->createPatient();
        $setup = $this->createDoctorWithClinicAndSchedule();
        $this->createAppointmentForPatient($patient, $setup, ['status' => 'completed']);

        $this->authenticateAs($patient)
            ->postJson('/api/disputes', [
                'doctor_id' => $setup['doctor']->id,
                'subject' => 'Appointment issue',
                'description' => 'Doctor did not show up for the appointment.',
                'priority' => 'high',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Dispute created successfully');

        $this->assertDatabaseHas('disputes', [
            'complainant_id' => $patient->id,
            'doctor_id' => $setup['doctor']->id,
            'subject' => 'Appointment issue',
            'status' => 'open',
        ]);
    }

    public function test_create_dispute_requires_authentication(): void
    {
        $this->postJson('/api/disputes', [])->assertUnauthorized();
    }

    public function test_create_dispute_validates_required_fields(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/disputes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'description']);
    }

    public function test_create_dispute_requires_doctor_or_pharmacist_target(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/disputes', [
                'subject' => 'Missing target',
                'description' => 'No defendant provided',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'doctor_id or pharmacist_id is required');
    }
}
