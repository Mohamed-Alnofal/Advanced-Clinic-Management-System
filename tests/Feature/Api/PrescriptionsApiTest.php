<?php

namespace Tests\Feature\Api;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Prescriptions\Models\PrescriptionItem;
use Modules\Prescriptions\Models\Prescriptions;
use Tests\TestCase;

class PrescriptionsApiTest extends TestCase
{
    protected function createVisitSetup(): array
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patientRecord = $this->createPatientRecord($this->createPatient());
        $patientFile = $this->createPatientFile($patientRecord, $setup['doctor']);

        $visit = MedicalVisits::create([
            'patient_file_id' => $patientFile->id,
            'doctor_id' => $setup['doctor']->id,
            'diagnosis' => 'Hypertension',
            'visit_date' => now()->toDateString(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
            'chief_complaint' => 'High blood pressure',
        ]);

        return array_merge($setup, compact('patientRecord', 'patientFile', 'visit'));
    }

    protected function prescriptionPayload(int $visitId): array
    {
        return [
            'medical_visit_id' => $visitId,
            'items' => [
                [
                    'medicine_name' => 'Amlodipine',
                    'dose' => '5mg',
                    'frequency' => 'once daily',
                    'duration' => '30 days',
                    'instructions' => 'Take after breakfast',
                ],
            ],
        ];
    }

    public function test_doctor_can_create_prescription(): void
    {
        $setup = $this->createVisitSetup();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/prescriptions', $this->prescriptionPayload($setup['visit']->id))
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertDatabaseHas('prescriptions', [
            'medical_visit_id' => $setup['visit']->id,
        ]);

        $this->assertDatabaseHas('prescription_items', [
            'medicine_name' => 'Amlodipine',
            'dose' => '5mg',
        ]);
    }

    public function test_prescription_store_requires_authentication(): void
    {
        $this->postJson('/api/prescriptions', [])->assertUnauthorized();
    }

    public function test_prescription_store_validates_required_fields(): void
    {
        $setup = $this->createVisitSetup();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/prescriptions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'medical_visit_id',
                'items',
            ]);
    }

    public function test_doctor_can_delete_prescription(): void
    {
        $setup = $this->createVisitSetup();

        $prescription = Prescriptions::create([
            'medical_visit_id' => $setup['visit']->id,
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => 'Ibuprofen',
            'dose' => '400mg',
            'frequency' => 'twice daily',
            'duration' => '5 days',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->deleteJson('/api/prescriptions/'.$prescription->id)
            ->assertOk()
            ->assertJsonPath('message', 'Prescription deleted successfully');

        $this->assertDatabaseMissing('prescriptions', [
            'id' => $prescription->id,
        ]);
    }
}
