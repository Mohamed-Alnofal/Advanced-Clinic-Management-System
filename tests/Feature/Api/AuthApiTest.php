<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_login_returns_jwt_for_verified_patient(): void
    {
        $patient = $this->createPatient([
            'email' => 'login_patient@acms.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'login_patient@acms.test',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'email', 'name'],
            ]);
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createPatient(['email' => 'wrong@acms.test']);

        $this->postJson('/api/login', [
            'email' => 'wrong@acms.test',
            'password' => 'invalid-password',
        ])->assertUnauthorized();
    }

    public function test_register_creates_unverified_user(): void
    {
        $email = $this->uniqueEmail('register');

        $response = $this->postJson('/api/register', [
            'name' => 'New Patient',
            'email' => $email,
            'password' => 'password123',
            'role' => 'user',
            'phone' => $this->uniquePhone(),
            'date_of_birth' => '1998-05-12',
            'gender' => 'male',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('requires_otp', true);
        $this->assertJsonMessageContains($response, 'User registered successfully');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'is_verified' => 0,
            'status' => 'active',
        ]);
    }

    public function test_register_doctor_skips_otp_and_waits_for_admin_review(): void
    {
        $specialization = \Modules\Core\Models\specializations::create(['name' => 'Dermatology']);
        $email = $this->uniqueEmail('doctor-register');

        $response = $this->postJson('/api/register', [
            'name' => 'Dr Pending',
            'email' => $email,
            'password' => 'password123',
            'role' => 'doctor',
            'phone' => $this->uniquePhone(),
            'date_of_birth' => '2005-03-20',
            'gender' => 'female',
            'specialization_id' => $specialization->id,
            'bio' => 'Skin specialist',
            'experience_years' => 5,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('requires_otp', false)
            ->assertJsonPath('verification_status', 'pending');
        $this->assertJsonMessageContains($response, 'pending admin review');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'status' => 'pending',
            'is_verified' => 1,
        ]);
        $this->assertDatabaseHas('doctors', [
            'is_verified' => 0,
            'specialization_id' => $specialization->id,
        ]);
    }

    public function test_verify_otp_activates_account(): void
    {
        $user = $this->createUnverifiedUserWithOtp('654321');

        $this->postJson('/api/verify-otp', [
            'email' => $user->email,
            'otp' => '654321',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Account verified successfully');

        $this->assertTrue((bool) $user->fresh()->is_verified);
    }

    public function test_verify_otp_validates_email_and_otp_format(): void
    {
        $this->postJson('/api/verify-otp', [
            'email' => 'not-an-email',
            'otp' => '12',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'otp']);
    }
}
