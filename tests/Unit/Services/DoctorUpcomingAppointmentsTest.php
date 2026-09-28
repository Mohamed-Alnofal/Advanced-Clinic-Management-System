<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Modules\Appointments\Services\DoctorAppointmentService;
use Modules\Consultation\Models\Consultation;
use Tests\TestCase;

class DoctorUpcomingAppointmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC', 'consultation.timezone' => 'UTC']);
        date_default_timezone_set('UTC');
    }

    public function test_upcoming_includes_today_slot_after_start_time_while_window_open(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 10:05:00'));

        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => [
                'day' => 'tuesday',
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'consultation_duration' => 30,
                'consultation_type' => 'video',
            ],
        ]);
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_type' => 'video',
            'appointment_date' => '2026-08-18',
        ]);
        Consultation::create([
            'appointment_id' => $appointment->id,
            'room_name' => 'room-test',
            'status' => 'scheduled',
        ]);

        $rows = (new DoctorAppointmentService())->getUpcomingAppointments($setup['doctor']->id);

        $this->assertTrue($rows->contains(fn ($row) => (int) $row['id'] === $appointment->id));
        $this->assertSame('2026-08-18', $rows->firstWhere('id', $appointment->id)['appointment_date']);
        $this->assertSame('2026-08-18', $rows->firstWhere('id', $appointment->id)['day']);

        Carbon::setTestNow();
    }

    public function test_upcoming_includes_tomorrow_morning_when_now_is_afternoon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 15:00:00'));

        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => [
                'day' => 'wednesday',
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'consultation_duration' => 30,
                'consultation_type' => 'video',
            ],
        ]);
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_type' => 'video',
            'appointment_date' => '2026-08-19',
        ]);

        $rows = (new DoctorAppointmentService())->getUpcomingAppointments($setup['doctor']->id);

        $this->assertTrue($rows->contains(fn ($row) => (int) $row['id'] === $appointment->id));

        Carbon::setTestNow();
    }

    public function test_upcoming_excludes_slot_after_grace_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 10:45:00'));

        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => [
                'day' => 'tuesday',
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'consultation_duration' => 30,
            ],
        ]);
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_date' => '2026-08-18',
        ]);

        $rows = (new DoctorAppointmentService())->getUpcomingAppointments($setup['doctor']->id);

        $this->assertFalse($rows->contains(fn ($row) => (int) $row['id'] === $appointment->id));

        Carbon::setTestNow();
    }
}
