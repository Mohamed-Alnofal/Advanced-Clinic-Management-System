<?php

namespace Tests\Feature\Api;

use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Models\PatientAccessRequest;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientService;
use Tests\TestCase;

class MedicalRecordPrivacyTest extends TestCase
{
    private function seedChart(): array
    {
        $docA = $this->createDoctorUser();
        $docB = $this->createDoctorUser(['name' => 'Doctor B']);
        $patientUser = $this->createPatient(['name' => 'Privacy Patient']);

        /** @var PatientService $patients */
        $patients = app(PatientService::class);
        $created = $patients->create($patientUser, $docA['doctor']->id);
        $patient = $created['patient'];
        $file = $created['patient_file'];

        // Ensure auth()->user()->patient works
        $patients->createForUser($patientUser);
        $patientUser->load('patient');

        $privateVisit = MedicalVisits::create([
            'patient_file_id' => $file->id,
            'doctor_id' => $docA['doctor']->id,
            'chief_complaint' => 'Private complaint',
            'diagnosis' => 'Private diagnosis',
            'visit_date' => now(),
            'category' => 'psychiatry',
            'visibility' => 'private',
        ]);

        $approvedVisit = MedicalVisits::create([
            'patient_file_id' => $file->id,
            'doctor_id' => $docA['doctor']->id,
            'chief_complaint' => 'Needs approval',
            'diagnosis' => 'Migraine',
            'visit_date' => now(),
            'category' => 'general',
            'visibility' => 'approved_doctors',
        ]);

        $publicVisit = MedicalVisits::create([
            'patient_file_id' => $file->id,
            'doctor_id' => $docA['doctor']->id,
            'chief_complaint' => 'Public note',
            'diagnosis' => 'Cold',
            'visit_date' => now(),
            'category' => 'general',
            'visibility' => 'public',
        ]);

        return compact('docA', 'docB', 'patientUser', 'patient', 'file', 'privateVisit', 'approvedVisit', 'publicVisit');
    }

    public function test_patient_file_is_unique_per_patient(): void
    {
        $doc = $this->createDoctorUser();
        $patientUser = $this->createPatient();
        $patients = app(PatientService::class);
        $first = $patients->create($patientUser, $doc['doctor']->id);
        $second = $patients->create($patientUser, $doc['doctor']->id);

        $this->assertSame($first['patient_file']->id, $second['patient_file']->id);
        $this->assertSame(1, PatientFile::where('patient_id', $first['patient']->id)->count());
    }

    public function test_doctor_create_visit_defaults_to_approved_doctors_without_visibility_input(): void
    {
        $ctx = $this->seedChart();

        $response = $this->authenticateAs($ctx['docA']['doctorUser'])
            ->postJson('/api/medical-visits', [
                'patient_file_id' => $ctx['file']->id,
                'chief_complaint' => 'Cough',
                'diagnosis' => 'Flu',
                'visit_date' => now()->toDateTimeString(),
                'category' => 'general',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.visibility', 'approved_doctors');
    }

    public function test_other_doctor_cannot_see_private_or_unapproved_visits_on_chart(): void
    {
        $ctx = $this->seedChart();

        // Doc B gains chart access via public visit existence
        $response = $this->authenticateAs($ctx['docB']['doctorUser'])
            ->getJson('/api/patient-files/'.$ctx['file']->id);

        $response->assertOk();
        $ids = collect($response->json('medical_visits'))->pluck('id')->all();

        $this->assertContains($ctx['publicVisit']->id, $ids);
        $this->assertNotContains($ctx['privateVisit']->id, $ids);
        $this->assertNotContains($ctx['approvedVisit']->id, $ids);
    }

    public function test_access_request_flow_unlocks_approved_doctors_visit(): void
    {
        $ctx = $this->seedChart();

        $this->authenticateAs($ctx['docB']['doctorUser'])
            ->postJson('/api/patient-access/request', [
                'visit_id' => $ctx['approvedVisit']->id,
            ])
            ->assertOk();

        $requestId = PatientAccessRequest::where('medical_visit_id', $ctx['approvedVisit']->id)
            ->where('doctor_id', $ctx['docB']['doctor']->id)
            ->value('id');

        $this->authenticateAs($ctx['patientUser'])
            ->postJson('/api/patient-access/approve', ['request_id' => $requestId])
            ->assertOk();

        $this->authenticateAs($ctx['docB']['doctorUser'])
            ->getJson('/api/medical-visits/'.$ctx['approvedVisit']->id)
            ->assertOk()
            ->assertJsonPath('data.id', $ctx['approvedVisit']->id);
    }

    public function test_patient_can_change_visit_visibility(): void
    {
        $ctx = $this->seedChart();

        $this->authenticateAs($ctx['patientUser'])
            ->patchJson('/api/medical-visits/'.$ctx['privateVisit']->id.'/visibility', [
                'visibility' => 'public',
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'public');

        $this->authenticateAs($ctx['docB']['doctorUser'])
            ->getJson('/api/medical-visits/'.$ctx['privateVisit']->id)
            ->assertOk();
    }

    public function test_doctor_cannot_change_visibility(): void
    {
        $ctx = $this->seedChart();

        $this->authenticateAs($ctx['docA']['doctorUser'])
            ->patchJson('/api/medical-visits/'.$ctx['approvedVisit']->id.'/visibility', [
                'visibility' => 'public',
            ])
            ->assertForbidden();
    }

    public function test_export_respects_doctor_visibility_filter(): void
    {
        $ctx = $this->seedChart();

        $export = $this->authenticateAs($ctx['docB']['doctorUser'])
            ->getJson('/api/patients/'.$ctx['patient']->id.'/export')
            ->assertOk()
            ->json('data.medical_visits');

        $ids = collect($export)->pluck('id')->all();
        $this->assertContains($ctx['publicVisit']->id, $ids);
        $this->assertNotContains($ctx['privateVisit']->id, $ids);
    }

    public function test_family_history_is_scoped_not_global(): void
    {
        $ctx = $this->seedChart();
        $other = $this->seedChart();

        $this->authenticateAs($ctx['docA']['doctorUser'])
            ->postJson('/api/family-histories', [
                'patient_file_id' => $ctx['file']->id,
                'relative' => 'Father',
                'disease' => 'Diabetes',
            ])
            ->assertCreated();

        $list = $this->authenticateAs($ctx['docA']['doctorUser'])
            ->getJson('/api/family-histories')
            ->assertOk()
            ->json('data.data');

        foreach ($list as $row) {
            $this->assertSame($ctx['file']->id, $row['patient_file_id']);
            $this->assertNotSame($other['file']->id, $row['patient_file_id']);
        }
    }
}
