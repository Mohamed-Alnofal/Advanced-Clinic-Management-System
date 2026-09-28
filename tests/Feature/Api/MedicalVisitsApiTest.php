<?php

namespace Tests\Feature\Api;

use Modules\MedicalVisits\Models\MedicalVisits;
use Tests\TestCase;

class MedicalVisitsApiTest extends TestCase
{
    protected function createVisitSetup(): array
    {
        $doctorSetup = $this->createDoctorWithClinicAndSchedule();
        $patientRecord = $this->createPatientRecord($this->createPatient());
        $patientFile = $this->createPatientFile($patientRecord, $doctorSetup['doctor']);

        return array_merge($doctorSetup, compact('patientRecord', 'patientFile'));
    }

    public function test_doctor_can_create_medical_visit(): void
    {
        $setup = $this->createVisitSetup();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/medical-visits', [
                'patient_file_id' => $setup['patientFile']->id,
                'diagnosis' => 'Type 2 Diabetes',
                'visit_date' => now()->toDateString(),
                'category' => 'general',
                'chief_complaint' => 'Fatigue',
            ])
            ->assertCreated()
            ->assertJsonPath('data.visibility', 'approved_doctors');

        $visit = MedicalVisits::query()
            ->where('patient_file_id', $setup['patientFile']->id)
            ->where('doctor_id', $setup['doctor']->id)
            ->first();

        $this->assertNotNull($visit);
        $this->assertSame('Type 2 Diabetes', $visit->diagnosis);
        $this->assertSame('approved_doctors', $visit->visibility);
    }

    public function test_medical_visit_store_requires_authentication(): void
    {
        $this->postJson('/api/medical-visits', [])->assertUnauthorized();
    }

    public function test_medical_visit_store_validates_required_fields(): void
    {
        $setup = $this->createVisitSetup();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/medical-visits', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'patient_file_id',
                'diagnosis',
                'visit_date',
                'category',
            ]);
    }

    public function test_doctor_can_list_medical_visits(): void
    {
        $setup = $this->createVisitSetup();

        MedicalVisits::create([
            'patient_file_id' => $setup['patientFile']->id,
            'doctor_id' => $setup['doctor']->id,
            'diagnosis' => 'Asthma',
            'visit_date' => now()->toDateTimeString(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->getJson('/api/medical-visits')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_doctor_can_update_medical_visit(): void
    {
        $setup = $this->createVisitSetup();

        $visit = MedicalVisits::create([
            'patient_file_id' => $setup['patientFile']->id,
            'doctor_id' => $setup['doctor']->id,
            'diagnosis' => 'Initial diagnosis',
            'visit_date' => now()->toDateTimeString(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/medical-visits/'.$visit->id, [
                'diagnosis' => 'Updated diagnosis',
            ])
            ->assertOk()
            ->assertJsonPath('data.diagnosis', 'Updated diagnosis');
    }

    public function test_doctor_can_delete_medical_visit(): void
    {
        $setup = $this->createVisitSetup();

        $visit = MedicalVisits::create([
            'patient_file_id' => $setup['patientFile']->id,
            'doctor_id' => $setup['doctor']->id,
            'diagnosis' => 'To be deleted',
            'visit_date' => now()->toDateTimeString(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->deleteJson('/api/medical-visits/'.$visit->id)
            ->assertOk();

        $this->assertDatabaseMissing('medical_visits', ['id' => $visit->id]);
    }
}
