<?php

namespace Modules\Auth\Services;

use Modules\Core\Services\OtpService;
use Modules\Users\Models\User;
use Modules\Doctors\Models\Doctor;
use Modules\Pharmacist\Models\Pharmacist;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

use Modules\Auth\DTOs\RegisterDTO;
use App\Support\PublicMedia;

class AuthService
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

   
    public function register(RegisterDTO $dto)
    {
        $result = DB::transaction(function () use ($dto) {

            $skipEmailOtp = in_array($dto->role, ['doctor', 'ambulance', 'pharmacist'], true);

            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'phone' => filled($dto->phone) ? $dto->phone : null,
                'date_of_birth' => $dto->date_of_birth,
                'gender' => $dto->gender,
                // Providers skip email OTP; admin review gates portal access.
                'is_verified' => $skipEmailOtp,
                'status' => in_array($dto->role, ['doctor', 'pharmacist', 'ambulance'], true)
                    ? 'pending'
                    : 'active',
            ]);

            Role::findOrCreate($dto->role, 'api');
            $user->assignRole($dto->role);

            if (! $skipEmailOtp) {
                $otp = $this->otpService->generate();
                $this->otpService->assign($user, $otp);
            }

            if ($dto->role === 'doctor') {
                $doctorPayload = [
                    'user_id' => $user->id,
                    'specialization_id' => $dto->specialization_id,
                    'bio' => $dto->bio,
                    'experience_years' => $dto->experience_years,
                    'image' => $dto->image,
                    'is_verified' => false,
                    
                ];

               

                $doctor = Doctor::create($doctorPayload);

                if (! empty($dto->medical_certificates)) {
                    foreach ($dto->medical_certificates as $file) {
                        $doctor->certificates()->create([
                            'file_path' => $file,
                        ]);
                    }
                }

                return [
                    'message' => 'Doctor registration request submitted. Your application is pending admin review.',
                    'requires_otp' => false,
                    'verification_status' => 'pending',
                    'role' => $dto->role,
                    'user' => $user,
                    'doctor' => $doctor->load('certificates'),
                ];
            }

            if ($dto->role === 'ambulance') {
                return [
                    'message' => 'Operator registration submitted. After admin approval you can sign in.',
                    'requires_otp' => false,
                    'verification_status' => 'pending',
                    'role' => $dto->role,
                    'user' => $user,
                ];
            }

            if ($dto->role === 'pharmacist') {
                $pharmacistPayload = [
                    'user_id' => $user->id,
                    'license_number' => $dto->license_number,
                    'image' => $dto->image,
                    'is_verified' => false,
                ];
                if (filled($dto->pharmacy_name)) {
                    $pharmacistPayload['pharmacy_name'] = $dto->pharmacy_name;
                }
                if (filled($dto->clinic_address)) {
                    $pharmacistPayload['clinic_address'] = $dto->clinic_address;
                }
                if ($dto->governorate_id) {
                    $pharmacistPayload['governorate_id'] = $dto->governorate_id;
                }

                $pharmacist = Pharmacist::create($pharmacistPayload);

                return [
                    'message' => 'Pharmacist registration submitted. After admin approval you can sign in and add pharmacy locations.',
                    'requires_otp' => false,
                    'verification_status' => 'pending',
                    'role' => $dto->role,
                    'user' => $user,
                    'pharmacist' => $pharmacist,
                ];
            }

            return [
                'message' => 'User registered successfully. Please verify your email.',
                'requires_otp' => true,
                'role' => $dto->role,
                'user' => $user,
            ];
        });

        if (! empty($result['user'])) {
            $fresh = $result['user']->fresh();
            $role = $result['role'] ?? null;
            if ($fresh && $fresh->otp && ! in_array($role, ['doctor', 'ambulance', 'pharmacist'], true)) {
                $this->otpService->send($fresh, (int) $fresh->otp);
            }
        }

        unset($result['role']);

        return $result;
    }

    public function verifyOtp(string $email, string $otp): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'User not found with this email address',
            ]);
        }

        return $this->otpService->verify($user, $otp);
    }

    public function resendOtp(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'User not found with this email address',
            ]);
        }

        return $this->otpService->resend($user);
    }

    /**
     * Login with email or phone.
     * Doctors/pharmacists may log in while pending/rejected (email OTP done)
     * so the app can show application status / reupload UI.
     * Full clinical features remain gated by is_verified on the role model.
     */
    public function login($identifier, $password)
    {
        $identifier = trim((string) $identifier);

        $user = User::with(['doctor.certificates', 'pharmacist', 'roles'])
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier);
                if ($identifier !== '') {
                    $q->orWhere('phone', $identifier);
                }
            })
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if (! Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Global suspend gate — applies to all roles (patient, doctor, pharmacist, …).
        if (strtolower((string) ($user->status ?? '')) === 'suspended') {
            $reason = trim((string) ($user->suspension_reason ?? ''));

            return response()->json([
                'message' => $reason !== ''
                    ? 'Your account has been suspended. '.$reason
                    : 'Your account has been suspended. Contact support for help.',
                'code' => 'account_suspended',
                'verification_status' => 'suspended',
                'suspension_reason' => $reason !== '' ? $reason : null,
            ], 403);
        }

        // Patients must complete email OTP first.
        if ($user->hasRole('user') && ! $user->is_verified) {
            return response()->json([
                'message' => 'Your account is not verified yet. Please verify the code sent to your email.',
                'requires_otp' => true,
                'email' => $user->email,
            ], 403);
        }

        if ($user->hasRole('doctor')) {
            if (! $user->doctor) {
                return response()->json([
                    'message' => 'Doctor profile not found',
                ], 404);
            }

            // Email OTP required before status tracking.
            // Legacy rejects cleared users.is_verified — still allow pending/rejected doctors in.
            if (
                ! $user->is_verified &&
                ! in_array($user->status, ['pending', 'rejected'], true)
            ) {
                return response()->json([
                    'message' => 'Please verify your email before signing in.',
                    'requires_otp' => true,
                    'email' => $user->email,
                ], 403);
            }

            $state = $user->doctor->state();
            if (! $state->canLogin()) {
                return response()->json([
                    'message' => $state->message(),
                    'verification_status' => $user->status ?? 'suspended',
                ], 403);
            }
        }

        if ($user->hasRole('pharmacist')) {
            if (! $user->pharmacist) {
                return response()->json([
                    'message' => 'Pharmacist profile not found',
                ], 404);
            }

            // Email OTP only when still required (legacy accounts).
            if (
                ! $user->is_verified &&
                ! in_array($user->status, ['pending', 'rejected', 'accepted'], true)
            ) {
                return response()->json([
                    'message' => 'Please verify your email before signing in.',
                    'requires_otp' => true,
                    'email' => $user->email,
                ], 403);
            }

            // Mirror doctor: allow pending/rejected login so they can reupload documents.
            $status = strtolower((string) ($user->status ?? 'pending'));
            if ($status === 'suspended') {
                return response()->json([
                    'message' => 'Your pharmacist account has been suspended.',
                    'verification_status' => 'suspended',
                ], 403);
            }
        }

        if ($user->hasRole('ambulance')) {
            $status = strtolower((string) ($user->status ?? 'pending'));

            if ($status === 'rejected') {
                return response()->json([
                    'message' => 'Your ambulance account was rejected. Contact support for help.',
                    'verification_status' => 'rejected',
                ], 403);
            }

            if ($status !== 'accepted') {
                return response()->json([
                    'message' => 'Ambulance account waiting for admin approval.',
                    'verification_status' => 'pending',
                ], 403);
            }
        }

        $token = JWTAuth::fromUser($user);

        $user->makeHidden([
            'password',
            'otp',
            'otp_expires_at',
        ]);

        $payload = [
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user,
        ];

        if ($user->doctor) {
            $payload['verification_status'] = $this->doctorVerificationStatus($user);
            $payload['rejection_reason'] = $user->status === 'rejected'
                ? ($user->suspension_reason ?: null)
                : null;
        }

        if ($user->pharmacist) {
            $payload['verification_status'] = $this->pharmacistVerificationStatus($user);
            $payload['rejection_reason'] = $user->status === 'rejected'
                ? ($user->suspension_reason ?: null)
                : null;
            $payload['pharmacist'] = [
                'id' => $user->pharmacist->id,
                'pharmacy_name' => $user->pharmacist->pharmacy_name,
                'license_number' => $user->pharmacist->license_number,
                'is_verified' => (bool) $user->pharmacist->is_verified,
            ];
        }

        return $payload;
    }

    public function uploadPharmacyDocuments(Pharmacist $pharmacist, array $data, ?string $imagePath): array
    {
        return DB::transaction(function () use ($pharmacist, $data, $imagePath) {
            if ($imagePath) {
                $pharmacist->image = $imagePath;
            }
            if (! empty($data['pharmacy_name'])) {
                $pharmacist->pharmacy_name = $data['pharmacy_name'];
            }
            if (! empty($data['license_number'])) {
                $pharmacist->license_number = $data['license_number'];
            }
            if (! empty($data['clinic_address'])) {
                $pharmacist->clinic_address = $data['clinic_address'];
            }
            if (! empty($data['governorate_id'])) {
                $pharmacist->governorate_id = (int) $data['governorate_id'];
            }

            $pharmacist->is_verified = false;
            $pharmacist->save();

            $user = $pharmacist->user;
            $user->update([
                'status' => 'pending',
                'suspension_reason' => null,
                'is_verified' => true,
            ]);

            return [
                'message' => 'Documents uploaded successfully, pending review',
                'verification_status' => 'pending',
                'pharmacist' => [
                    'id' => $pharmacist->id,
                    'pharmacy_name' => $pharmacist->pharmacy_name,
                    'license_number' => $pharmacist->license_number,
                    'is_verified' => false,
                ],
            ];
        });
    }

    private function pharmacistVerificationStatus(User $user): string
    {
        if ($user->pharmacist?->is_verified && in_array($user->status, ['accepted', 'active'], true)) {
            return 'approved';
        }

        return strtolower((string) ($user->status ?? 'pending'));
    }

    public function uploadDocuments(Doctor $doctor, array $data, ?string $imagePath, array $certificatePaths): array
    {
        return DB::transaction(function () use ($doctor, $data, $imagePath, $certificatePaths) {
            if ($imagePath) {
                $doctor->image = $imagePath;
            }

            if (! empty($data['clinic_name'])) {
                $doctor->clinic_name = $data['clinic_name'];
            }
            if (! empty($data['clinic_address'])) {
                $doctor->clinic_address = $data['clinic_address'];
            }
            if (! empty($data['governorate_id'])) {
                $doctor->governorate_id = (int) $data['governorate_id'];
            }

            $doctor->is_verified = false;
            $doctor->save();

            if (! empty($certificatePaths)) {
                // Replace previous certificates with the new submission set.
                $doctor->certificates()->delete();
                foreach ($certificatePaths as $path) {
                    $doctor->certificates()->create(['file_path' => $path]);
                }
            }

            $user = $doctor->user;
            $user->update([
                'status' => 'pending',
                'suspension_reason' => null,
                'is_verified' => true, // email already verified; keep true
            ]);

            $doctor->load('certificates');

            return [
                'message' => 'Documents uploaded successfully, pending review',
                'doctor' => [
                    'id' => $doctor->id,
                    'is_verified' => (bool) $doctor->is_verified,
                    'rejection_reason' => null,
                    'image_url' => PublicMedia::url($doctor->image),
                    'certificates' => $doctor->certificates->map(fn ($c) => [
                        'id' => $c->id,
                        'file_path' => $c->file_path,
                        'url' => PublicMedia::url($c->file_path),
                    ])->values(),
                ],
                'verification_status' => 'pending',
            ];
        });
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return [
            'message' => 'Logged out successfully',
        ];
    }

    public function refreshToken()
    {
        $newToken = JWTAuth::refresh(JWTAuth::getToken());

        return [
            'access_token' => $newToken,
            'token_type' => 'bearer',
        ];
    }

    private function doctorVerificationStatus(User $user): string
    {
        if ($user->doctor?->is_verified) {
            return 'approved';
        }

        $status = strtolower((string) ($user->status ?? 'pending'));

        return match ($status) {
            'accepted', 'approved' => 'approved',
            'rejected' => 'rejected',
            'suspended' => 'suspended',
            default => 'pending',
        };
    }
}
