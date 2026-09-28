<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Modules\Consultation\Models\Consultation;
use Modules\Consultation\Services\ConsultationJoinPolicy;
use Tests\TestCase;

class ConsultationJoinPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_doctor_cannot_join_hours_before_the_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 08:00:00', 'Asia/Damascus'));
        config(['consultation.timezone' => 'Asia/Damascus', 'app.timezone' => 'Asia/Damascus']);

        [$appointment, $consultation, $doctorUser] = $this->makeVideoAppointment('2026-08-19', '10:00:00');

        $response = ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, $doctorUser);

        $this->assertNotNull($response);
        $this->assertSame('consultation_too_early', $response->getData(true)['code']);
    }

    public function test_doctor_can_join_fifteen_minutes_before(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 09:50:00', 'Asia/Damascus'));
        config(['consultation.timezone' => 'Asia/Damascus', 'app.timezone' => 'Asia/Damascus']);

        [$appointment, $consultation, $doctorUser] = $this->makeVideoAppointment('2026-08-19', '10:00:00');

        $this->assertNull(
            ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, $doctorUser)
        );
    }

    public function test_patient_cannot_join_until_fifteen_minutes_before(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 08:00:00', 'Asia/Damascus'));
        config(['consultation.timezone' => 'Asia/Damascus', 'app.timezone' => 'Asia/Damascus']);

        [$appointment, $consultation, , $patient] = $this->makeVideoAppointment('2026-08-19', '10:00:00');

        $response = ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, $patient);

        $this->assertNotNull($response);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('consultation_too_early', $response->getData(true)['code']);
    }

    public function test_patient_can_join_fifteen_minutes_before(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 09:50:00', 'Asia/Damascus'));
        config(['consultation.timezone' => 'Asia/Damascus', 'app.timezone' => 'Asia/Damascus']);

        [$appointment, $consultation, , $patient] = $this->makeVideoAppointment('2026-08-19', '10:00:00');

        $this->assertNull(
            ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, $patient)
        );
    }

    public function test_cannot_join_after_the_scheduled_slot_ends(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 10:31:00', 'Asia/Damascus'));
        config(['consultation.timezone' => 'Asia/Damascus', 'app.timezone' => 'Asia/Damascus']);

        [$appointment, $consultation, $doctorUser] = $this->makeVideoAppointment('2026-08-19', '10:00:00');

        $response = ConsultationJoinPolicy::denyJoinResponse($appointment, $consultation, $doctorUser);

        $this->assertNotNull($response);
        $this->assertSame('consultation_window_ended', $response->getData(true)['code']);
    }

    /**
     * @return array{0: mixed, 1: Consultation, 2: mixed, 3: mixed}
     */
    private function makeVideoAppointment(string $date, string $startTime): array
    {
        $setup = $this->createDoctorWithClinicAndSchedule([
            'schedule' => [
                'day' => 'wednesday',
                'start_time' => $startTime,
                'end_time' => '10:30:00',
                'consultation_duration' => 30,
                'consultation_type' => 'video',
            ],
        ]);
        $patient = $this->createPatient();
        $appointment = $this->createAppointmentForPatient($patient, $setup, [
            'status' => 'accepted',
            'appointment_type' => 'video',
            'appointment_date' => $date,
        ]);
        $consultation = Consultation::create([
            'appointment_id' => $appointment->id,
            'room_name' => 'room-join-test',
            'status' => 'scheduled',
        ]);

        return [
            $appointment->fresh(['schedule', 'doctor', 'consultation']),
            $consultation,
            $setup['doctorUser'],
            $patient,
        ];
    }
}
