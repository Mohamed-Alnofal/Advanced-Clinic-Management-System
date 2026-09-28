<?php

namespace Tests\Feature\Api;

use Modules\Doctors\Models\DoctorHoliday;
use Modules\Doctors\Models\DoctorMonthlySchedule;
use Tests\TestCase;

class DoctorMonthlyScheduleApiTest extends TestCase
{
    public function test_doctor_can_create_and_list_holidays(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();

        $create = $this->authenticateAs($setup['doctorUser'])->postJson('/api/doctors/holidays', [
            'clinic_id' => $setup['clinic']->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-15',
            'reason' => 'Vacation',
        ]);

        $create->assertOk()->assertJsonPath('reason', 'Vacation');
        $this->assertDatabaseHas('doctor_holidays', [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'reason' => 'Vacation',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->getJson('/api/doctors/holidays')
            ->assertOk()
            ->assertJsonFragment(['reason' => 'Vacation']);
    }

    public function test_holiday_blocks_patient_booking_and_hides_weekly_slots(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $this->giveDoctorActiveSubscription($setup['doctor']);
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 200);

        DoctorHoliday::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'reason' => 'Day off',
        ]);

        $this->authenticateAs($patient)
            ->getJson('/api/doctor/schedule/'.$setup['doctor']->id.'?date=2026-09-14')
            ->assertOk()
            ->assertJsonPath('schedules', []);

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'clinic_id' => $setup['clinic']->id,
                'schedule_id' => $setup['schedule']->id,
                'booking_for' => 'self',
                'appointment_type' => 'clinic',
                'appointment_date' => '2026-09-14',
            ])
            ->assertUnprocessable();
    }

    public function test_generate_month_marks_holidays_and_groups_patient_slots(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => [
                'day' => 'monday',
                'start_time' => '10:00:00',
                'end_time' => '10:30:00',
                'consultation_duration' => 30,
            ],
        ]);
        $this->giveDoctorActiveSubscription($setup['doctor']);
        $patient = $this->createPatient();

        DoctorHoliday::create([
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'reason' => 'Vacation',
        ]);

        $this->authenticateAs($setup['doctorUser'])
            ->postJson('/api/doctors/monthly-schedule/generate', [
                'clinic_id' => $setup['clinic']->id,
                'month' => '2026-09',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('month', '2026-09');

        $this->assertGreaterThan(0, DoctorMonthlySchedule::where('doctor_id', $setup['doctor']->id)->count());
        $this->assertTrue(
            DoctorMonthlySchedule::query()
                ->where('doctor_id', $setup['doctor']->id)
                ->whereDate('date', '2026-09-14')
                ->where('status', 'holiday')
                ->exists()
        );

        $day = $this->authenticateAs($patient)
            ->getJson('/api/doctors/'.$setup['doctor']->id.'/available-slots?date=2026-09-07');
        $day->assertOk();
        $this->assertIsArray($day->json());
        $this->assertNotEmpty($day->json());
        $this->assertEquals($setup['schedule']->id, $day->json('0.schedule_id'));

        $holidayDay = $this->authenticateAs($patient)
            ->getJson('/api/doctors/'.$setup['doctor']->id.'/available-slots?date=2026-09-14');
        $holidayDay->assertOk();
        $this->assertSame([], $holidayDay->json());

        $month = $this->authenticateAs($patient)
            ->getJson('/api/doctors/'.$setup['doctor']->id.'/monthly-available-slots?month=2026-09');
        $month->assertOk();
        $this->assertIsArray($month->json());
        $dates = collect($month->json())->pluck('date')->all();
        $this->assertContains('2026-09-07', $dates);
        $this->assertNotContains('2026-09-14', $dates);
        $first = collect($month->json())->firstWhere('date', '2026-09-07');
        $this->assertIsArray($first);
        $this->assertIsArray($first['slots']);
        $this->assertCount(1, $first['slots']);
    }

    public function test_patient_calendar_returns_day_summaries(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $this->giveDoctorActiveSubscription($setup['doctor']);
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->getJson('/api/doctors/'.$setup['doctor']->id.'/calendar?month=2026-09')
            ->assertOk()
            ->assertJsonPath('month', '2026-09')
            ->assertJsonStructure(['month', 'days', 'holidays']);
    }
}
