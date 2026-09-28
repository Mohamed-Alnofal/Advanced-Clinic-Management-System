<?php

namespace Tests\Feature\Api;

use Modules\Doctors\Models\DoctorSchedule;
use Tests\TestCase;

class AppointmentRescheduleTest extends TestCase
{
    public function test_patient_can_reschedule_to_available_slot(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();

        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'appointment_type' => 'clinic',
        ]);

        $this->assertNotNull($appointment->id);

        $newSchedule = DoctorSchedule::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'day' => 'tuesday',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'consultation_duration' => 30,
            'price' => 60,
            'consultation_type' => 'clinic',
            'is_booked' => false,
        ]);

        $response = $this->authenticateAs($patient)->postJson('/api/appointment/reschedule', [
            'appointment_id' => $appointment->id,
            'new_schedule_id' => $newSchedule->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Appointment rescheduled successfully');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'schedule_id' => $newSchedule->id,
        ]);

        $this->assertFalse((bool) DoctorSchedule::find($setup['schedule']->id)->is_booked);
        $this->assertTrue((bool) $newSchedule->fresh()->is_booked);
    }

    public function test_reschedule_rejects_already_booked_target_slot(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();

        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'appointment_type' => 'clinic',
        ]);

        $bookedSchedule = DoctorSchedule::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'day' => 'wednesday',
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'consultation_duration' => 30,
            'price' => 70,
            'consultation_type' => 'clinic',
            'is_booked' => true,
        ]);

        $response = $this->authenticateAs($patient)
            ->postJson('/api/appointment/reschedule', [
                'appointment_id' => $appointment->id,
                'new_schedule_id' => $bookedSchedule->id,
            ]);

        if ($response->status() === 200) {
            $response->assertJsonPath('status', false);
            $response->assertJsonPath('message', 'Selected slot is already booked');
        } else {
            $response->assertStatus(422);
        }
    }

    public function test_reschedule_rejects_type_mismatch_video_to_clinic(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => ['consultation_type' => 'video'],
        ]);
        $patient = $this->createPatient();

        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'appointment_type' => 'video',
            'clinic_id' => $setup['clinic']->id,
        ]);

        $clinicSlot = DoctorSchedule::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'day' => 'thursday',
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'consultation_duration' => 30,
            'price' => 80,
            'consultation_type' => 'clinic',
            'is_booked' => false,
        ]);

        $this->authenticateAs($patient)
            ->postJson('/api/appointment/reschedule', [
                'appointment_id' => $appointment->id,
                'new_schedule_id' => $clinicSlot->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_schedule_id']);
    }

    public function test_reschedule_requires_authentication(): void
    {
        $this->postJson('/api/appointment/reschedule', [])
            ->assertUnauthorized();
    }

    public function test_reschedule_validates_required_fields(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/appointment/reschedule', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['appointment_id', 'new_schedule_id']);
    }
}
