<?php

// namespace Modules\Auth\Http\Controllers;
namespace Modules\Auth\Http\Controllers;
use Modules\Core\Http\Controllers\Controller;
use Modules\Auth\Services\AuthService;
use Modules\Auth\DTOs\RegisterDTO;
use App\Support\PublicMedia;
use App\DTOs\LoginDTO;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    private $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }
    
 public function register(Request $request, AuthService $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:30',
                    'date_of_birth' => [
            'nullable',
            'date',
            'after_or_equal:' . now()->subYears(120)->toDateString(),
            'before_or_equal:2010-12-31',
        ],
            'gender' => 'required|in:male,female',
            'role' => 'required|in:user,doctor,pharmacist,ambulance',
            
            'specialization_id' => 'required_if:role,doctor|nullable|integer|exists:specializations,id',
            'pharmacy_name' => 'required_if:role,pharmacist|nullable|string|max:255',
            'license_number' => 'required_if:role,pharmacist|nullable|string|max:100',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'medical_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'medical_certificates' => 'nullable|array',
            'medical_certificates.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $imagePath = null;
        $certificatePaths = [];

        // 📸 image
        if ($request->hasFile('image')) {
            $folder = $request->role === 'pharmacist' ? 'pharmacists/profile' : 'doctors/profile';
            $imagePath = PublicMedia::store($request->file('image'), $folder);
        }

        // 📄 certificates — accept singular + plural field names from clients
        if ($request->hasFile('medical_certificates')) {
            $files = $request->file('medical_certificates');
            if (! is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $file) {
                if ($file) {
                    $certificatePaths[] = PublicMedia::store($file, 'doctors/certificates');
                }
            }
        }
        if ($request->hasFile('medical_certificate')) {
            $certificatePaths[] = PublicMedia::store(
                $request->file('medical_certificate'),
                'doctors/certificates'
            );
        }

        $dto = new RegisterDTO(
            $request->name,
            $request->email,
            $request->password,
            $request->role,
            $request->phone,
            $request->date_of_birth,
            $request->gender,
            $request->specialization_id ? (int) $request->specialization_id : null,
            $request->bio,
            $request->experience_years ? (int) $request->experience_years : null,
            $imagePath,
            $certificatePaths,
            $request->pharmacy_name,
            $request->license_number,
            $request->clinic_address,
            $request->governorate_id ? (int) $request->governorate_id : null,
            $request->clinic_name,
        );

        try {
            return response()->json($service->register($dto), 201);
        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? $e->getCode();
            if ((string) $sqlState === '23000') {
                throw ValidationException::withMessages([
                    'email' => 'This email or phone is already registered.',
                ]);
            }
            throw $e;
        }
    }  

    // public function login(Request $request, AuthService $service)
    // {
    //     $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required'
    //     ]);

    //     return response()->json($service->login($request->email, $request->password));
    // }

   public function login(Request $request, AuthService $service)
    {
        $request->validate([
            'email' => 'required|string', // email or phone
            'password' => 'required',
        ]);

        $identifier = (string) $request->email;

        // Per-account lockout — soft enough for local/demo; still blocks brute force.
        $key = 'login:'.Str::lower($identifier).'|'.$request->ip();
        $maxAttempts = 20;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many login attempts. Try again after {$seconds} seconds.",
                'code' => 'rate_limited',
                'retry_after' => $seconds,
            ], 429);
        }

        $response = $service->login(
            $identifier,
            $request->password
        );

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            RateLimiter::hit($key, $decaySeconds);

            return $response;
        }

        RateLimiter::clear($key);

        $accessToken = is_array($response) ? ($response['access_token'] ?? $response['token'] ?? null) : null;
        $omitTokenFromBody = $request->boolean('omit_token')
            || $request->header('X-Auth-Transport') === 'cookie';

        if ($omitTokenFromBody && is_array($response)) {
            unset($response['access_token'], $response['token']);
        }

        $json = response()->json($response);

        if (config('jwt.use_httponly_cookie') && is_string($accessToken) && $accessToken !== '') {
            $minutes = (int) config('jwt.cookie_minutes', 10080);
            $json->cookie(
                config('jwt.cookie_name', 'acms_token'),
                $accessToken,
                $minutes,
                '/',
                config('jwt.cookie_domain'),
                (bool) config('jwt.cookie_secure', false),
                true, // HttpOnly — not readable by JS (XSS mitigation)
                false,
                (string) config('jwt.cookie_same_site', 'lax'),
            );
        }

        return $json;
    }
  public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6'
        ]);

        try {
            $result = $this->authService->verifyOtp(
                $request->email,
                $request->otp
            );
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

   public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        try {
            $result = $this->authService->resendOtp($request->email);
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    // public function approveAccount($id)
    // {
    //     $result = $this->authService->approveAccount($id);
    //     return response()->json($result);
    // }

    public function logout(Request $request, AuthService $service)
    {
        $response = response()->json($service->logout());

        return $response->withoutCookie(config('jwt.cookie_name', 'acms_token'));
    }


    public function uploadDocuments(Request $request, AuthService $service)
{
    $user = auth()->user();

    if (!$user || !$user->doctor) {
        return response()->json([
            'message' => 'Doctor not found'
        ], 404);
    }

    $doctor = $user->doctor->loadMissing('user');

    $state = $doctor->state();

    if (! $state->canUploadDocuments()) {
        return response()->json([
            'success' => false,
            'message' => $state->message(),
            'verification_status' => $user->status ?? 'pending',
        ], 403);
    }

    $request->validate([
        'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        'medical_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        'medical_certificates' => 'nullable|array',
        'medical_certificates.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        'clinic_name' => 'nullable|string|max:255',
        'clinic_address' => 'nullable|string|max:500',
        'governorate_id' => 'nullable|integer|exists:governorates,id',
    ]);

    $imagePath = null;
    $certificatePaths = [];

    if ($request->hasFile('image')) {
        $imagePath = PublicMedia::store($request->file('image'), 'doctors/profile');
    }

    if ($request->hasFile('medical_certificates')) {
        $files = $request->file('medical_certificates');
        if (! is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $file) {
            if ($file) {
                $certificatePaths[] = PublicMedia::store($file, 'doctors/certificates');
            }
        }
    }
    if ($request->hasFile('medical_certificate')) {
        $certificatePaths[] = PublicMedia::store(
            $request->file('medical_certificate'),
            'doctors/certificates'
        );
    }

    if (! $imagePath && empty($certificatePaths)) {
        return response()->json([
            'message' => 'Please upload at least one document (certificate or profile photo).',
        ], 422);
    }

    return response()->json(
        $service->uploadDocuments(
            $doctor,
            $request->all(),
            $imagePath,
            $certificatePaths
        )
    );
}



public function uploadPharmacyDocuments(Request $request, AuthService $service)
{
    $user = auth()->user();

    if (!$user || !$user->pharmacist) {
        return response()->json([
            'message' => 'Pharmacist not found'
        ], 404);
    }

    $pharmacist = $user->pharmacist;
    $status = strtolower((string) ($user->status ?? 'pending'));

    // Pending or rejected pharmacists may re-submit verification materials.
    if (! in_array($status, ['pending', 'rejected'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Documents can only be re-uploaded while your application is pending or rejected.',
        ], 403);
    }

    $request->validate([
        'pharmacy_name' => 'nullable|string|max:255',
        'license_number' => 'nullable|string|max:100',
        'clinic_address' => 'nullable|string|max:500',
        'governorate_id' => 'nullable|integer|exists:governorates,id',
        'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = PublicMedia::store($request->file('image'), 'pharmacists/profile');
    }

    return response()->json(
        $service->uploadPharmacyDocuments(
            $pharmacist,
            $request->all(),
            $imagePath
        )
    );
}

public function refreshToken(Request $request, AuthService $service)
{
    $payload = $service->refreshToken();
    $accessToken = $payload['access_token'] ?? null;
    $omitTokenFromBody = $request->boolean('omit_token')
        || $request->header('X-Auth-Transport') === 'cookie';

    if ($omitTokenFromBody) {
        unset($payload['access_token'], $payload['token']);
    }

    $json = response()->json($payload);

    if (config('jwt.use_httponly_cookie') && is_string($accessToken) && $accessToken !== '') {
        $minutes = (int) config('jwt.cookie_minutes', 10080);
        $json->cookie(
            config('jwt.cookie_name', 'acms_token'),
            $accessToken,
            $minutes,
            '/',
            config('jwt.cookie_domain'),
            (bool) config('jwt.cookie_secure', false),
            true,
            false,
            (string) config('jwt.cookie_same_site', 'lax'),
        );
    }

    return $json;
}
}