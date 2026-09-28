<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Pillar 1 — Authentication & RBAC isolation.
 */
class AuthRbacIsolationApiTest extends TestCase
{
    public static function protectedPostRoutesProvider(): array
    {
        return [
            'book appointment' => ['/api/appointments/book', []],
            'wallet top-up' => ['/api/wallet/top-up', ['amount' => 10]],
            'medical visit create' => ['/api/medical-visits', []],
            'ophthalmology create' => ['/api/ophthalmology-records', []],
            'prescription create' => ['/api/prescriptions', []],
            'doctor schedule create' => ['/api/doctor/schedule', []],
            'fcm token' => ['/api/fcm/store', ['token' => 'x']],
            'reschedule' => ['/api/appointment/reschedule', []],
        ];
    }

    /**
     * @dataProvider protectedPostRoutesProvider
     */
    public function test_protected_post_routes_reject_unauthenticated_requests(string $uri, array $payload): void
    {
        $this->postJson($uri, $payload)->assertUnauthorized();
    }

    public static function protectedGetRoutesProvider(): array
    {
        return [
            'wallet balance' => ['/api/wallet/getBalance'],
            'medical visits list' => ['/api/medical-visits'],
            'notifications' => ['/api/notifications'],
            'my appointments' => ['/api/patient/appointments'],
            'ophthalmology list' => ['/api/ophthalmology-records'],
        ];
    }

    /**
     * @dataProvider protectedGetRoutesProvider
     */
    public function test_protected_get_routes_reject_unauthenticated_requests(string $uri): void
    {
        $this->getJson($uri)->assertUnauthorized();
    }

    public function test_patient_cannot_create_medical_visit(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $patientRecord = $this->createPatientRecord($patient);
        $patientFile = $this->createPatientFile($patientRecord, $setup['doctor']);

        $this->authenticateAs($patient)
            ->postJson('/api/medical-visits', [
                'patient_file_id' => $patientFile->id,
                'diagnosis' => 'Should be forbidden',
                'visit_date' => now()->toDateString(),
                'category' => 'general',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only doctors can create medical visits');
    }

    public function test_patient_cannot_create_ophthalmology_record(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/ophthalmology-records', [
                'medical_visit_id' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only doctors can create ophthalmology records');
    }

    public function test_patient_cannot_approve_doctors_as_admin(): void
    {
        $patient = $this->createPatient();
        $doctorSetup = $this->createDoctorUser(['is_verified' => false], ['is_verified' => false]);

        $this->authenticateAs($patient)
            ->postJson('/api/admin/verification/doctors/'.$doctorSetup['doctor']->id.'/approve')
            ->assertForbidden();
    }

    public function test_doctor_token_can_reach_medical_visit_create_validation(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();

        // Authenticated doctor passes RBAC and hits validation (not 401/403).
        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/medical-visits', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_file_id', 'diagnosis', 'visit_date']);
    }
}
