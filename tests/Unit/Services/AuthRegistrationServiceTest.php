<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Mail;
use Modules\Auth\Mail\SendOtpMail;
use Modules\Auth\Services\AuthService;
use Modules\Core\Services\OtpService;
use Modules\Core\Models\specializations;
use Modules\Users\Models\User;
use Tests\TestCase;

class AuthRegistrationServiceTest extends TestCase
{
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->authService = app(AuthService::class);
    }

    public function test_patient_registration_assigns_active_status_and_sends_otp(): void
    {
        $email = $this->uniqueEmail('patient-reg');

        $result = $this->authService->register(new \Modules\Auth\DTOs\RegisterDTO(
            name: 'Patient One',
            email: $email,
            password: 'password123',
            role: 'user',
            phone: $this->uniquePhone(),
            date_of_birth: '1998-05-12',
            gender: 'male',
            specialization_id: null,
            bio: null,
            experience_years: null,
            image: null,
            medical_certificates: [],
            pharmacy_name: null,
            license_number: null,
            clinic_address: null,
            governorate_id: null,
            clinic_name: null,
        ));

        $this->assertStringContainsString('User registered successfully', $result['message']);
        $this->assertTrue($result['requires_otp']);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('active', $user->status);
        $this->assertFalse((bool) $user->is_verified);
        $this->assertNotNull($user->otp);

        Mail::assertSent(SendOtpMail::class, fn (SendOtpMail $mail) => $mail->hasTo($email));
    }

    public function test_doctor_registration_skips_otp_and_marks_pending_review(): void
    {
        $specialization = specializations::create(['name' => 'Cardiology']);

        $email = $this->uniqueEmail('doctor-reg');

        $result = $this->authService->register(new \Modules\Auth\DTOs\RegisterDTO(
            name: 'Doctor One',
            email: $email,
            password: 'password123',
            role: 'doctor',
            phone: $this->uniquePhone(),
            date_of_birth: '1988-03-20',
            gender: 'male',
            specialization_id: $specialization->id,
            bio: 'Experienced cardiologist',
            experience_years: 8,
            image: null,
            medical_certificates: [],
            pharmacy_name: null,
            license_number: null,
            clinic_address: null,
            governorate_id: null,
            clinic_name: null,
        ));

        $this->assertStringContainsString('pending admin review', strtolower($result['message']));
        $this->assertFalse($result['requires_otp']);
        $this->assertSame('pending', $result['verification_status']);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('pending', $user->status);
        $this->assertTrue((bool) $user->is_verified);
        $this->assertNull($user->otp);

        Mail::assertNothingSent();
        $this->assertDatabaseHas('doctors', [
            'user_id' => $user->id,
            'specialization_id' => $specialization->id,
            'is_verified' => 0,
        ]);
    }
}
