<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Modules\Doctors\Models\DoctorSchedule;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    public function test_authenticated_patient_can_book_available_appointment_slot(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 200.00);

        $response = $this->authenticateAs($patient)->postJson('/api/appointments/book', [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $setup['schedule']->id,
            'booking_for' => 'self',
            'appointment_type' => 'clinic',
            'payment_method' => 'pay_at_clinic',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Appointment booked successfully')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'appointment_id',
                    'schedule_id',
                    'doctor_id',
                    'clinic_id',
                    'status',
                    'booking_for',
                ],
            ]);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $patient->id,
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $setup['schedule']->id,
            'status' => 'pending',
            'booking_for' => 'self',
        ]);

        // Weekly templates stay reusable; a booking locks the calendar date, not the row.
        $this->assertFalse(
            (bool) DoctorSchedule::find($setup['schedule']->id)->is_booked
        );
        $this->assertNotNull(
            \Modules\Appointments\Models\Appointment::where('schedule_id', $setup['schedule']->id)
                ->value('appointment_date')
        );
    }

    public function test_booking_requires_authentication(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();

        $this->postJson('/api/appointments/book', [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $setup['schedule']->id,
        ])->assertUnauthorized();
    }

    public function test_booking_validates_required_fields(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/book', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['doctor_id', 'schedule_id']);
    }

    public function test_booking_rejects_already_booked_slot(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $firstPatient = $this->createPatient();
        $this->createWalletForUser($firstPatient, 200.00);
        $secondPatient = $this->createPatient();
        $this->createWalletForUser($secondPatient, 200.00);

        $payload = [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $setup['schedule']->id,
            'booking_for' => 'self',
            'appointment_type' => 'clinic',
            'payment_method' => 'pay_at_clinic',
        ];

        $this->authenticateAs($firstPatient)
            ->postJson('/api/appointments/book', $payload)
            ->assertOk()
            ->assertJsonPath('status', true);

        $response = $this->authenticateAs($secondPatient)->postJson('/api/appointments/book', $payload);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'هذا الموعد محجوز مسبقاً لهذا التاريخ. اختر وقتاً آخر.');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_booking_for_relative_requires_patient_details(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'clinic_id' => $setup['clinic']->id,
                'schedule_id' => $setup['schedule']->id,
                'booking_for' => 'relative',
                'appointment_type' => 'clinic',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'age', 'gender']);
    }

    public function test_patient_can_book_appointment_for_relative(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 200.00);

        $response = $this->authenticateAs($patient)->postJson('/api/appointments/book', [
            'doctor_id' => $setup['doctor']->id,
            'clinic_id' => $setup['clinic']->id,
            'schedule_id' => $setup['schedule']->id,
            'booking_for' => 'relative',
            'appointment_type' => 'clinic',
            'name' => 'Ahmad Relative',
            'age' => 30,
            'gender' => 'male',
            'payment_method' => 'pay_at_clinic',
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $patient->id,
            'booking_for' => 'relative',
        ]);

        $relative = \Modules\Users\Models\Relatives::where('user_id', $patient->id)->first();
        $this->assertNotNull($relative);
        $this->assertSame('Ahmad Relative', $relative->name);
    }

    public function test_same_weekly_slot_can_be_booked_on_a_different_date(): void
    {
        $setup = $this->createDoctorWithClinicAndSchedule();
        $firstPatient = $this->createPatient();
        $this->createWalletForUser($firstPatient, 200.00);
        $secondPatient = $this->createPatient();
        $this->createWalletForUser($secondPatient, 200.00);

        $firstDate = now()->next('monday')->toDateString();
        $laterDate = now()->next('monday')->addWeek()->toDateString();

        $this->authenticateAs($firstPatient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'clinic_id' => $setup['clinic']->id,
                'schedule_id' => $setup['schedule']->id,
                'booking_for' => 'self',
                'appointment_type' => 'clinic',
                'appointment_date' => $firstDate,
                'payment_method' => 'pay_at_clinic',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->authenticateAs($secondPatient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'clinic_id' => $setup['clinic']->id,
                'schedule_id' => $setup['schedule']->id,
                'booking_for' => 'self',
                'appointment_type' => 'clinic',
                'appointment_date' => $laterDate,
                'payment_method' => 'pay_at_clinic',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_booking_keeps_date_from_appointment_time_instead_of_next_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 12:00:00'));

        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => ['day' => 'tuesday'],
        ]);
        $patient = $this->createPatient();
        $this->createWalletForUser($patient, 200.00);

        $this->authenticateAs($patient)
            ->postJson('/api/appointments/book', [
                'doctor_id' => $setup['doctor']->id,
                'clinic_id' => $setup['clinic']->id,
                'schedule_id' => $setup['schedule']->id,
                'booking_for' => 'self',
                'appointment_type' => 'clinic',
                'appointment_time' => '2026-08-18 10:00',
                'payment_method' => 'pay_at_clinic',
            ])
            ->assertOk()
            ->assertJsonPath('data.appointment_date', '2026-08-18');

        Carbon::setTestNow();
    }
}
