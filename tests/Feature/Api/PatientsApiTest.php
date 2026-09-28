<?php

namespace Tests\Feature\Api;

use Modules\MedicalVisits\Models\MedicalVisits;
use Tests\TestCase;

class PatientsApiTest extends TestCase
{
    public function test_authenticated_user_can_view_patient_history(): void
    {
        $patientUser = $this->createPatient();
        $doctorSetup = $this->createDoctorWithClinicAndSchedule();
        $patientRecord = $this->createPatientRecord($patientUser);
        $patientFile = $this->createPatientFile($patientRecord, $doctorSetup['doctor']);

        MedicalVisits::create([
            'patient_file_id' => $patientFile->id,
            'doctor_id' => $doctorSetup['doctor']->id,
            'diagnosis' => 'Hypertension',
            'visit_date' => now()->toDateTimeString(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
        ]);

        $this->authenticateAs($patientUser)
            ->getJson('/api/patients/'.$patientRecord->id.'/history')
            ->assertOk();
    }

    public function test_patient_history_requires_authentication(): void
    {
        $patientRecord = $this->createPatientRecord($this->createPatient());

        $this->getJson('/api/patients/'.$patientRecord->id.'/history')
            ->assertUnauthorized();
    }

    public function test_doctor_can_store_patient_file(): void
    {
        $doctorSetup = $this->createDoctorWithClinicAndSchedule();
        $patientRecord = $this->createPatientRecord($this->createPatient());

        $this->authenticateAs($doctorSetup['doctorUser'])
            ->postJson('/api/patient-files', [
                'patient_id' => $patientRecord->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('patient_files', [
            'patient_id' => $patientRecord->id,
            'created_by' => $doctorSetup['doctor']->id,
        ]);
    }

    public function test_store_patient_file_validates_required_fields(): void
    {
        $doctorSetup = $this->createDoctorWithClinicAndSchedule();

        $this->authenticateAs($doctorSetup['doctorUser'])
            ->postJson('/api/patient-files', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id']);
    }
}
