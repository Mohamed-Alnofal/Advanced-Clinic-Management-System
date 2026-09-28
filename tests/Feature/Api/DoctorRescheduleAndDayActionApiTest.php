<?php

namespace Tests\Feature\Api;

use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\AppointmentReschedule;
use Tests\TestCase;

class DoctorRescheduleAndDayActionApiTest extends TestCase
{
    public function test_doctor_can_propose_reschedule_and_patient_can_accept(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-09-02',
        ]);

        $propose = $this->authenticateAs($setup['doctorUser'])->postJson(
            '/api/appointments/'.$appointment->id.'/reschedule',
            [
                'new_date' => '2026-09-12',
                'new_time' => '11:00',
                'reason' => 'Doctor unavailable',
            ]
        );

        $propose->assertOk()
            ->assertJsonPath('appointment_id', $appointment->id)
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('new_date', '2026-09-12')
            ->assertJsonPath('new_time', '11:00');

        $rescheduleId = $propose->json('id');

        $accept = $this->authenticateAs($patient)->postJson(
            '/api/appointments/reschedules/'.$rescheduleId.'/accept'
        );

        $accept->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('reschedule.status', 'accepted');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'appointment_date' => '2026-09-12',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('appointment_reschedules', [
            'id' => $rescheduleId,
            'status' => 'accepted',
        ]);
    }

    public function test_patient_reject_keeps_original_appointment(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-09-02',
        ]);

        $reschedule = AppointmentReschedule::create([
            'appointment_id' => $appointment->id,
            'old_date' => '2026-09-02',
            'old_time' => '10:00:00',
            'new_date' => '2026-09-12',
            'new_time' => '11:00',
            'status' => 'pending',
            'reason' => 'Doctor unavailable',
            'created_by' => $setup['doctorUser']->id,
        ]);

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/reschedules/'.$reschedule->id.'/reject')
            ->assertOk()
            ->assertJsonPath('reschedule.status', 'rejected');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'appointment_date' => '2026-09-02',
            'status' => 'accepted',
        ]);
    }

    public function test_patient_cannot_propose_doctor_reschedule(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'appointment_date' => '2026-09-02',
        ]);

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/'.$appointment->id.'/reschedule', [
                'new_date' => '2026-09-12',
                'new_time' => '11:00',
            ])
            ->assertForbidden();
    }

    public function test_day_cancel_cancels_only_that_days_appointments(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $sameDay = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-08-16',
        ]);
        $otherDay = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-08-17',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/doctors/schedule/day-cancel', [
                'date' => '2026-08-16',
                'reason' => 'Emergency',
            ])
            ->assertOk()
            ->assertJsonPath('cancelled_count', 1)
            ->assertJsonPath('action.action', 'cancel');

        $this->assertSame('cancelled', Appointment::find($sameDay->id)->status);
        $this->assertSame('accepted', Appointment::find($otherDay->id)->status);
    }

    public function test_day_delay_creates_pending_requests_for_that_day_only(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-09-02',
        ]);
        $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-09-03',
        ]);

        $response = $this->authenticateAs($setup['doctorUser'])->postJson(
            '/api/doctors/schedule/day-delay',
            [
                'date' => '2026-09-02',
                'minutes' => 120,
                'reason' => 'Emergency',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('appointments_count', 1)
            ->assertJsonPath('requests.0.status', 'pending')
            ->assertJsonPath('requests.0.new_time', '12:00');
    }

    public function test_patient_list_includes_pending_reschedule(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-09-02',
        ]);

        AppointmentReschedule::create([
            'appointment_id' => $appointment->id,
            'old_date' => '2026-09-02',
            'old_time' => '10:00:00',
            'new_date' => '2026-09-12',
            'new_time' => '11:00',
            'status' => 'pending',
            'reason' => 'Doctor unavailable',
            'created_by' => $setup['doctorUser']->id,
        ]);

        $this->authenticateAs($patient)
            ->getJson('/api/patient/appointments')
            ->assertOk()
            ->assertJsonPath('data.0.pending_reschedule.status', 'pending')
            ->assertJsonPath('data.0.pending_reschedule.new_date', '2026-09-12');
    }
}
