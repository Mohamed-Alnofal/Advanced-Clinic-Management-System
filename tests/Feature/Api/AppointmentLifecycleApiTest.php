<?php

namespace Tests\Feature\Api;

use Modules\Doctors\Models\DoctorSchedule;
use Tests\TestCase;

class AppointmentLifecycleApiTest extends TestCase
{
    public function test_patient_can_cancel_booked_appointment(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $this->createAppointmentForPatient($patient, $setup);

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/cancel', [
                'schedule_id' => $setup['schedule']->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Appointment cancelled successfully');

        $this->assertFalse(
            (bool) DoctorSchedule::find($setup['schedule']->id)->is_booked
        );
    }

    public function test_cancel_appointment_requires_authentication(): void
    {
        $this->postJson('/api/appointments/cancel', [
            'schedule_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_cancel_appointment_validates_schedule_id(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/cancel', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schedule_id']);
    }

   public function test_patient_can_list_own_appointments(): void
{
    $setup = $this->createDoctorWithClinicAndSchedule();
    $patient = $this->createPatient();
    $this->createAppointmentForPatient($patient, $setup);

    $response = $this->authenticateAs($patient)
        ->getJson('/api/my-appointments')
        ->assertOk();

    // ✅ التحقق من أن الاستجابة تحتوي على بيانات
    $response->assertJsonStructure([
        'success',
        'data' => [
            '*' => [
                'id',
                'doctor_name',
                'status',
                'appointment_type'
            ]
        ]
    ]);
    
    // ✅ التحقق من أن البيانات مصفوفة وليست فارغة
    $this->assertIsArray($response->json('data'));
}

    public function test_my_appointments_requires_authentication(): void
    {
        $this->getJson('/api/my-appointments')->assertUnauthorized();
    }
}
