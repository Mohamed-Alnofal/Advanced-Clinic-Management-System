<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Mail\SendOtpMail;
use Modules\Core\Services\OtpService;
use Modules\Users\Models\User;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->otpService = new OtpService();
        Mail::fake();
    }

    public function test_generate_returns_six_digit_otp(): void
    {
        $otp = $this->otpService->generate();

        $this->assertGreaterThanOrEqual(100000, $otp);
        $this->assertLessThanOrEqual(999999, $otp);
    }

    public function test_assign_persists_otp_and_expiry_on_user(): void
    {
        $user = $this->createUnverifiedUser();

        $this->otpService->assign($user, 123456);

        $user->refresh();

        $this->assertSame('123456', $user->otp);
        $this->assertNotNull($user->otp_expires_at);
        $this->assertTrue(Carbon::parse($user->otp_expires_at)->isFuture());
    }

    public function test_send_dispatches_otp_email(): void
    {
        $user = $this->createUnverifiedUser();

        $this->otpService->send($user, 654321);

        Mail::assertSent(SendOtpMail::class, function (SendOtpMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_verify_activates_account_with_valid_otp(): void
    {
        $user = $this->createUnverifiedUser([
            'otp' => '111222',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $result = $this->otpService->verify($user, '111222');

        $this->assertSame('Account verified successfully', $result['message']);

        $user->refresh();
        $this->assertTrue((bool) $user->is_verified);
        $this->assertNull($user->otp);
        $this->assertNull($user->otp_expires_at);
    }

    public function test_verify_rejects_invalid_otp(): void
    {
        $user = $this->createUnverifiedUser([
            'otp' => '111222',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->otpService->verify($user, '999999');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('otp', $exception->errors());
            throw $exception;
        }
    }

    public function test_verify_rejects_expired_otp(): void
    {
        $user = $this->createUnverifiedUser([
            'otp' => '111222',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->otpService->verify($user, '111222');
        } catch (ValidationException $exception) {
            $this->assertSame('OTP expired', $exception->errors()['otp'][0]);
            throw $exception;
        }
    }

    public function test_verify_returns_message_when_already_verified(): void
    {
        $user = $this->createUnverifiedUser(['is_verified' => true]);

        $result = $this->otpService->verify($user, '000000');

        $this->assertSame('Already verified', $result['message']);
    }

    public function test_resend_generates_new_otp_and_sends_email(): void
    {
        $user = $this->createUnverifiedUser([
            'otp' => '111111',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $result = $this->otpService->resend($user);

        $this->assertSame('OTP resent', $result['message']);

        $user->refresh();
        $this->assertNotSame('111111', $user->otp);
        $this->assertTrue(Carbon::parse($user->otp_expires_at)->isFuture());

        Mail::assertSent(SendOtpMail::class);
    }

    public function test_resend_skips_when_user_already_verified(): void
    {
        $user = $this->createUnverifiedUser(['is_verified' => true]);

        $result = $this->otpService->resend($user);

        $this->assertSame('Already verified', $result['message']);
        Mail::assertNothingSent();
    }

    private function createUnverifiedUser(array $attributes = []): User
    {
        $suffix = uniqid();

        return User::create(array_merge([
            'name' => 'Unverified User',
            'email' => "unverified_{$suffix}@acms.test",
            'phone' => '08'.substr($suffix, -8),
            'password' => bcrypt('password'),
            'is_verified' => false,
            'status' => 'active',
        ], $attributes));
    }
}
