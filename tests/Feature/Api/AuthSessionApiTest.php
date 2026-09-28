<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthSessionApiTest extends TestCase
{
    public function test_authenticated_user_can_logout(): void
    {
        $patient = $this->createPatient();

        $this->authenticateAs($patient)
            ->postJson('/api/logout')
            ->assertOk();
    }

    public function test_refresh_token_returns_new_access_token(): void
    {
        $patient = $this->createPatient([
            'email' => 'refresh@acms.test',
            'password' => Hash::make('secret123'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'refresh@acms.test',
            'password' => 'secret123',
        ])->assertOk();

        $this->postJson('/api/refresh-token', [], [
            'Authorization' => 'Bearer '.$login->json('access_token'),
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);
    }

    public function test_resend_otp_sends_email_for_unverified_user(): void
    {
        Mail::fake();

        $user = $this->createUnverifiedUserWithOtp();

        $this->postJson('/api/resend-otp', [
            'email' => $user->email,
        ])
            ->assertOk();

        Mail::assertSent(\Modules\Auth\Mail\SendOtpMail::class);
    }

    public function test_resend_otp_validates_email(): void
    {
        $this->postJson('/api/resend-otp', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_works_with_valid_jwt(): void
    {
        $patient = $this->createPatient();
        $token = JWTAuth::fromUser($patient);

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->assertOk();
    }
}
