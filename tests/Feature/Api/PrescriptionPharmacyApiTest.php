<?php

namespace Tests\Feature\Api;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Prescriptions\Models\PrescriptionItem;
use Modules\Prescriptions\Models\Prescriptions;
use Modules\Prescriptions\Services\PrescriptionQrService;
use Tests\TestCase;

class PrescriptionPharmacyApiTest extends TestCase
{
    protected function createPrescriptionSetup(): array
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $patientRecord = $this->createPatientRecord($patient);
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

        $prescription = Prescriptions::create([
            'medical_visit_id' => $visit->id,
            'dispensing_type' => 'repeatable',
        ]);

        PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'medicine_name' => 'Amlodipine',
            'dose' => '5mg',
            'frequency' => 'once daily',
            'duration' => '30 days',
            'instructions' => 'Take after breakfast',
        ]);

        $qr = app(PrescriptionQrService::class)->generatePayload($prescription->id);

        return array_merge($setup, compact('patient', 'patientRecord', 'patientFile', 'visit', 'prescription', 'qr'));
    }

    public function test_doctor_and_patient_can_download_prescription_pdf(): void
    {
        $setup = $this->createPrescriptionSetup();

        $this->authenticateAs($setup['doctorUser'])
            ->get('/api/prescriptions/'.$setup['prescription']->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->authenticateAs($setup['patient'])
            ->get('/api/prescriptions/'.$setup['prescription']->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pharmacist_cannot_download_live_prescription_pdf(): void
    {
        $setup = $this->createPrescriptionSetup();
        $pharmacy = $this->createPharmacistWithPharmacy();

        $this->authenticateAs($pharmacy['pharmacistUser'])
            ->get('/api/prescriptions/'.$setup['prescription']->id.'/pdf')
            ->assertForbidden();
    }

    public function test_pharmacist_can_scan_qr_and_patient_cannot(): void
    {
        $setup = $this->createPrescriptionSetup();
        $pharmacy = $this->createPharmacistWithPharmacy();

        $this->authenticateAs($setup['patient'])
            ->postJson('/api/pharmacy/prescriptions/scan', ['qr' => $setup['qr']])
            ->assertForbidden();

        $this->authenticateAs($pharmacy['pharmacistUser'])
            ->postJson('/api/pharmacy/prescriptions/scan', ['qr' => $setup['qr']])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('prescription_id', $setup['prescription']->id)
            ->assertJsonPath('items.0.medicine_name', 'Amlodipine');
    }

    public function test_pharmacist_can_dispense_and_list_archives(): void
    {
        $setup = $this->createPrescriptionSetup();
        $pharmacy = $this->createPharmacistWithPharmacy();

        $dispense = $this->authenticateAs($pharmacy['pharmacistUser'])->postJson(
            '/api/pharmacy/prescriptions/dispense',
            [
                'qr' => $setup['qr'],
                'pharmacy_id' => $pharmacy['pharmacy']->id,
            ]
        );

        $dispense->assertOk()
            ->assertJsonPath('prescription_id', $setup['prescription']->id);

        $dispensingId = $dispense->json('dispensing_id');
        $this->assertNotEmpty($dispensingId);

        $this->authenticateAs($pharmacy['pharmacistUser'])
            ->getJson('/api/pharmacy/prescription-dispensings/archives?pharmacy_id='.$pharmacy['pharmacy']->id)
            ->assertOk()
            ->assertJsonPath('data.0.dispensing_id', $dispensingId)
            ->assertJsonPath('data.0.pharmacy_id', $pharmacy['pharmacy']->id);

        $this->authenticateAs($pharmacy['pharmacistUser'])
            ->get('/api/pharmacy/prescription-dispensings/'.$dispensingId.'/archive?pharmacy_id='.$pharmacy['pharmacy']->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pharmacist_cannot_dispense_to_another_pharmacy(): void
    {
        $setup = $this->createPrescriptionSetup();
        $mine = $this->createPharmacistWithPharmacy();
        $other = $this->createPharmacistWithPharmacy();

        $this->authenticateAs($mine['pharmacistUser'])
            ->postJson('/api/pharmacy/prescriptions/dispense', [
                'qr' => $setup['qr'],
                'pharmacy_id' => $other['pharmacy']->id,
            ])
            ->assertForbidden();
    }
}
