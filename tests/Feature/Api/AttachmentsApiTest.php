<?php

namespace Tests\Feature\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\MedicalVisits\Models\MedicalVisits;
use Tests\TestCase;

class AttachmentsApiTest extends TestCase
{
    protected function createMedicalVisitForDoctor(): array
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patientRecord = $this->createPatientRecord($this->createPatient());
        $patientFile = $this->createPatientFile($patientRecord, $setup['doctor']);

        $visit = MedicalVisits::create([
            'patient_file_id' => $patientFile->id,
            'doctor_id' => $setup['doctor']->id,
            'diagnosis' => 'Routine checkup',
            'visit_date' => now()->toDateString(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
            'chief_complaint' => 'Headache',
        ]);

        return array_merge($setup, compact('patientRecord', 'patientFile', 'visit'));
    }

    public function test_doctor_can_upload_attachment_for_medical_visit(): void
    {
        Storage::fake('public');
        $setup = $this->createMedicalVisitForDoctor();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/attachments/', [
                'medical_visit_id' => $setup['visit']->id,
                'type' => 'report',
                'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            ])
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertDatabaseHas('attachments', [
            'medical_visit_id' => $setup['visit']->id,
            'type' => 'report',
        ]);
    }

    public function test_attachment_store_requires_authentication(): void
    {
        $this->postJson('/api/attachments/', [])->assertUnauthorized();
    }

    public function test_attachment_store_validates_required_fields(): void
    {
        $setup = $this->createMedicalVisitForDoctor();

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/attachments/', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['medical_visit_id', 'file', 'type']);
    }
}
