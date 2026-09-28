<?php

namespace Tests\Feature\Api;

use Modules\Doctors\Models\DoctorSchedule;
use Tests\TestCase;

class DoctorScheduleApiTest extends TestCase
{
    public function test_doctor_can_store_schedule(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();

        $response = $this->authenticateAs($setup['doctorUser'])->postJson('/api/doctor/schedule', [
            'consultation_type' => 'clinic',
            'schedules' => [[
                'clinic_id' => $setup['clinic']->id,
                'day' => 'friday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'consultation_duration' => 30,
                'price' => 40,
            ]],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Doctor schedule generated successfully');

        $this->assertDatabaseHas('doctor_schedules', [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'day' => 'friday',
            'consultation_type' => 'clinic',
        ]);
    }

    public function test_store_schedule_requires_authentication(): void
    {
        $this->postJson('/api/doctor/schedule', [])
            ->assertUnauthorized();
    }

    public function test_store_schedule_validates_payload(): void
    {
        $doctor = $this->createDoctorWithClinicAndSchedule();

        $this->authenticateAs($doctor['doctorUser'])
            ->postJson('/api/doctor/schedule', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schedules', 'consultation_type']);
    }

    public function test_authenticated_user_can_view_doctor_schedule(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();

        DoctorSchedule::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'day' => 'saturday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'consultation_duration' => 30,
            'price' => 35,
            'consultation_type' => 'clinic',
            'is_booked' => false,
        ]);

        $this->authenticateAs($patient)
            ->getJson('/api/doctor/schedule/'.$setup['doctor']->id)
            ->assertOk()
            ->assertJsonPath('schedules', []);
    }

    public function test_patients_see_published_schedule_only_when_doctor_is_subscribed(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $this->giveDoctorActiveSubscription($setup['doctor']);
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/doctor/schedule/'.$setup['doctor']->id)
            ->assertOk()
            ->assertJsonFragment(['schedule_id' => $setup['schedule']->id]);
    }
}
