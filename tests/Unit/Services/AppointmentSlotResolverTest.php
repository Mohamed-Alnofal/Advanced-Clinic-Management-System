<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Services\AppointmentSlotResolver;
use Modules\Doctors\Models\DoctorSchedule;
use Tests\TestCase;

class AppointmentSlotResolverTest extends TestCase
{
    private AppointmentSlotResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new AppointmentSlotResolver();
    }

    public function test_resolve_date_uses_explicit_calendar_date(): void
    {
        $schedule = new DoctorSchedule([
            'day' => 'monday',
            'start_time' => '10:00:00',
        ]);

        $this->assertSame(
            '2026-09-14',
            $this->resolver->resolveDate($schedule, '2026-09-14')
        );
    }

    public function test_resolve_date_defaults_to_next_weekday_for_template(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00')); // Monday

        $schedule = new DoctorSchedule([
            'day' => 'monday',
            'start_time' => '10:00:00',
        ]);

        $this->assertSame(
            '2026-08-24',
            $this->resolver->resolveDate($schedule)
        );

        Carbon::setTestNow();
    }

    public function test_resolve_date_from_booking_uses_appointment_time_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00'));

        $schedule = new DoctorSchedule([
            'day' => 'tuesday',
            'start_time' => '10:00:00',
        ]);

        $this->assertSame(
            '2026-08-18',
            $this->resolver->resolveDateFromBooking($schedule, [
                'appointment_time' => '2026-08-18 10:00',
            ])
        );

        Carbon::setTestNow();
    }

    public function test_date_matches_schedule_weekday(): void
    {
        $schedule = new DoctorSchedule([
            'day' => 'tuesday',
            'start_time' => '10:00:00',
        ]);

        $this->assertTrue($this->resolver->dateMatchesScheduleDay($schedule, '2026-08-18'));
        $this->assertTrue($this->resolver->dateMatchesScheduleDay($schedule, '2026-08-25'));
        $this->assertFalse($this->resolver->dateMatchesScheduleDay($schedule, '2026-08-19'));
    }

    public function test_is_booked_for_date_ignores_cancelled_and_rejected(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $date = '2026-09-01';

        Appointment::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'user_id' => $patient->id,
            'schedule_id' => $setup['schedule']->id,
            'appointment_date' => $date,
            'status' => 'cancelled',
            'appointment_type' => 'clinic',
            'booking_for' => 'self',
        ]);

        $this->assertFalse(
            $this->resolver->isBookedForDate((int) $setup['schedule']->id, $date)
        );
    }

    public function test_is_booked_for_date_detects_active_booking(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $date = '2026-09-02';

        Appointment::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'user_id' => $patient->id,
            'schedule_id' => $setup['schedule']->id,
            'appointment_date' => $date,
            'status' => 'pending',
            'appointment_type' => 'clinic',
            'booking_for' => 'self',
        ]);

        $this->assertTrue(
            $this->resolver->isBookedForDate((int) $setup['schedule']->id, $date)
        );
    }

    public function test_same_weekly_template_allows_different_dates(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $firstDate = '2026-09-07';
        $secondDate = '2026-09-14';

        Appointment::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'user_id' => $patient->id,
            'schedule_id' => $setup['schedule']->id,
            'appointment_date' => $firstDate,
            'status' => 'pending',
            'appointment_type' => 'clinic',
            'booking_for' => 'self',
        ]);

        $this->assertTrue(
            $this->resolver->isBookedForDate((int) $setup['schedule']->id, $firstDate)
        );
        $this->assertFalse(
            $this->resolver->isBookedForDate((int) $setup['schedule']->id, $secondDate)
        );
    }
}
