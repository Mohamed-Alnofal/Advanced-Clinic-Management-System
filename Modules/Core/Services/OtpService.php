<?php
namespace Modules\Core\Services;

use Modules\Auth\Mail\SendOtpMail;
use Modules\Users\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public function generate(): int
    {
        return random_int(100000, 999999);
    }

    public function send(User $user, int $otp): void
    {
        try {
            Mail::to($user->email)->send(new SendOtpMail($otp));
        } catch (\Throwable $e) {
            // Never abort registration because SMTP is down — OTP is already stored on the user.
            Log::warning('OTP email failed; OTP kept on user record for verify-otp / admin.', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            if (app()->environment(['local', 'testing'])) {
                Log::info('DEV OTP (mail unavailable)', [
                    'email' => $user->email,
                    'otp' => $otp,
                ]);
            }
        }
    }

    public function assign(User $user, int $otp): void
    {
        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
    }

    public function verify(User $user, string $otp): array
    {
        if ($user->is_verified) {
            return ['message' => 'Already verified'];
        }

        if ($user->otp !== $otp) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp' => 'Invalid OTP'
            ]);
        }

        if (!$user->otp_expires_at || $user->otp_expires_at < now()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp' => 'OTP expired'
            ]);
        }

        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        // Doctors/pharmacists stay pending admin review after email OTP.
        if ($user->hasRole('doctor') || $user->hasRole('pharmacist')) {
            if (! in_array($user->status, ['accepted', 'rejected', 'suspended'], true)) {
                $user->update(['status' => 'pending']);
            }

            return [
                'message' => 'Email verified successfully. Your application is pending admin review.',
                'verification_status' => $user->fresh()->status ?? 'pending',
            ];
        }

        return ['message' => 'Account verified successfully'];
    }

    public function resend(User $user): array
    {
        if ($user->is_verified) {
            return ['message' => 'Already verified'];
        }

        $otp = $this->generate();

        $this->assign($user, $otp);
        $this->send($user, $otp);

        return ['message' => 'OTP resent'];
    }
}
