<?php

namespace Modules\HomeCare\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\HomeCare\Services\HomeCareProviderAvailabilityService;
use Modules\HomeCare\Services\HomeCareService;
use App\Support\PublicMedia;
use Modules\HomeCare\Models\HomeCareProfile;  // ✅ أضف هذا السطر
class HomeCareController
{
    public function __construct(
        protected HomeCareService $homeCareService,
        protected HomeCareProviderAvailabilityService $availabilityService,
    ) {}

/**
 * إنشاء توفر (يدعم: شهري، أسبوعي، يومي)
 */
public function createAvailability(Request $request)
{
    $user = Auth::user();

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $validated = $request->validate([
        'type' => 'required|string|in:weekly,monthly,daily',
        'day_of_week' => 'nullable|string|in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
        'month' => 'nullable|date_format:Y-m',
        'excluded_dates' => 'nullable|array',
        'excluded_dates.*' => 'date',
        'date' => 'nullable|date',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'is_available' => 'nullable|boolean',
    ]);

    // ✅ التحقق حسب النوع
    if ($validated['type'] === 'weekly' && empty($validated['day_of_week'])) {
        return response()->json([
            'success' => false,
            'message' => 'day_of_week is required for weekly availability.',
        ], 422);
    }

    if ($validated['type'] === 'monthly' && empty($validated['month'])) {
        return response()->json([
            'success' => false,
            'message' => 'month is required for monthly availability.',
        ], 422);
    }

    if ($validated['type'] === 'daily' && empty($validated['date'])) {
        return response()->json([
            'success' => false,
            'message' => 'date is required for daily availability.',
        ], 422);
    }

    try {
        $availability = $this->availabilityService->createAvailability($validated);

        $typeNames = [
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            'daily' => 'يومي'
        ];

        return response()->json([
            'success' => true,
            'message' => "تم إضافة التوفر {$typeNames[$validated['type']]} بنجاح.",
            'data' => $availability,
        ], 201);

    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 403);
    } catch (\InvalidArgumentException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}

public function getAvailableSlots(Request $request, $profileId)
{
    $user = Auth::user();

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $validated = $request->validate([
        'period' => 'required|in:monthly,weekly,daily',
        'date' => 'required|date|after_or_equal:today',
        'slot_duration' => 'nullable|integer|min:15|max:120',
    ]);

    $profile = HomeCareProfile::with(['availability', 'user', 'specialty'])
        ->where('id', $profileId)
        ->where('status', 'approved')
        ->firstOrFail();

    $availability = $this->homeCareService->getAllAvailableSlots(
        $profile,
        $validated['period'],
        $validated['date'],
        $validated['slot_duration'] ?? 60
    );

    return response()->json([
        'success' => true,
        'data' => [
            'provider' => [
                'id' => $profile->id,
                'full_name' => $profile->full_name,
                'provider_type' => $profile->provider_type,
                'visit_fee' => $profile->visit_fee,
                'experience_years' => $profile->experience_years,
                'bio' => $profile->bio,
            ],
            'period' => $validated['period'],
            'current_date' => $validated['date'],
            'availability' => $availability,
            'booking_instructions' => 'اختر الوقت المناسب واضغط على حجز',
        ],
    ]);
}

    public function createRequest(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'home_care_profile_id' => 'required|exists:home_care_profiles,id',
            'specialty_id' => 'nullable|exists:home_care_specialties,id',
            'patient_name' => 'nullable|string|max:255',
            'requested_date' => 'required|date|after_or_equal:today',
            'requested_time' => 'required',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $requestModel = $this->homeCareService->createRequest($user, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Home care request created successfully.',
                'data' => $requestModel,
            ], 201);
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            return response()->json([
                'success' => false,
                'message' => $first ?: $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

        public function myRequests()
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->homeCareService->patientRequests($user),
        ]);
    }

    /**
     * عرض طلبات مقدم الخدمة (للمقدم)
     */
    public function providerRequests()
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $requests = $this->homeCareService->providerRequests($user);

            return response()->json([
                'success' => true,
                'data' => $requests,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No home care profile found for this user.',
            ], 404);
        }
    }

    /**
     * تحديث حالة الطلب
     */
    public function updateRequestStatus(Request $request, $id)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected,cancelled,completed',
            'reject_reason' => 'nullable|string|max:1000',
            'cancel_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $requestModel = $this->homeCareService->updateRequestStatus($user, (int) $id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Request status updated.',
                'data' => $requestModel,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found.',
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }
    /**
 * تحديث الملف الشخصي لمقدم الرعاية المنزلية
 */
public function updateProfile(Request $request)
{
    $user = Auth::user();

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    // ✅ تحويل القيم الرقمية من string إلى numeric (لـ multipart/form-data)
    $request->merge([
        'experience_years' => $request->has('experience_years') ? (int) $request->input('experience_years') : null,
        'visit_fee' => $request->has('visit_fee') ? (float) $request->input('visit_fee') : null,
        'service_radius_km' => $request->has('service_radius_km') ? (float) $request->input('service_radius_km') : null,
        'latitude' => $request->has('latitude') ? (float) $request->input('latitude') : null,
        'longitude' => $request->has('longitude') ? (float) $request->input('longitude') : null,
    ]);

    $validated = $request->validate([
        'provider_type' => 'nullable|string|in:doctor,nurse,physiotherapist,lab,caregiver,general',
        'full_name' => 'nullable|string|max:255',
        'license_number' => 'nullable|string|max:100',
        'bio' => 'nullable|string|max:1000',
        'experience_years' => 'nullable|integer|min:0|max:60',
        'visit_fee' => 'nullable|numeric|min:0',
        'service_radius_km' => 'nullable|numeric|min:0',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
        'notes' => 'nullable|string|max:1000',
        'available_days' => 'nullable|array',
        'available_hours' => 'nullable|string|max:255',
    ]);

    // ✅ تحديث الملف الشخصي
    $profile = $this->homeCareService->updateProfile($user, $validated);

    return response()->json([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'data' => $profile,
    ]);
}
    public function nearbyProviders(Request $request)
{
    $validated = $request->validate([
        'latitude' => [
            'required',
            'numeric',
            'between:-90,90',
        ],

        'longitude' => [
            'required',
            'numeric',
            'between:-180,180',
        ],

        'specialty_id' => [
            'nullable',
            'exists:home_care_specialties,id',
        ],
    ]);

    $providers = $this->homeCareService->nearbyProviders(
        $validated['latitude'],
        $validated['longitude'],
        $validated['specialty_id'] ?? null
    );

    return response()->json([
        'success' => true,
        'data' => $providers,
    ]);
}
    public function specialties()
    {
        $specialties = $this->homeCareService->specialties();

        return response()->json([
            'success' => true,
            'data' => $specialties,
        ]);
    }

    public function index()
    {
        $user = Auth::user();
        $profiles = $this->homeCareService->approvedProfiles($user);

        return response()->json([
            'success' => true,
            'data' => $profiles,
        ]);
    }

    public function myProfile()
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $profile = $this->homeCareService->profileForUser($user);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

public function storeProfile(Request $request)
{
    $user = Auth::user();

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
        ], 401);
    }

    $validated = $request->validate([
        'specialty_id' => 'nullable|exists:home_care_specialties,id',
        'provider_type' => 'nullable|string|in:doctor,nurse,physiotherapist,lab,caregiver,general',
        'full_name' => 'nullable|string|max:255',
        'license_number' => 'nullable|string|max:100',
        'certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        'bio' => 'nullable|string|max:1000',
        'experience_years' => 'nullable|integer|min:0|max:60',
        'visit_fee' => 'nullable|numeric|min:0',
        'service_radius_km' => 'nullable|numeric|min:0',
        'available_days' => 'nullable|array',
        'available_hours' => 'nullable|string|max:255',
        'notes' => 'nullable|string|max:1000',
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
    ]);

    // ✅ تحقق من البيانات التي تمررها إلى Service
    \Log::info('Validated data:', $validated);

    $certificatePath = null;

    if ($request->hasFile('certificate')) {
        $certificatePath = PublicMedia::store($request->file('certificate'), 'home-care/certificates');
    }

    $payload = $validated;
    $payload['certificate_path'] = $certificatePath;

    $profile = $this->homeCareService->storeProfile($user, $payload);

    return response()->json([
        'success' => true,
        'message' => 'Home care profile submitted for review.',
        'data' => [
            'profile' => $profile,
            'certificate_path' => $certificatePath,
            'certificate_url' => $profile->certificate_url,
        ],
    ], 201);
}

public function deleteAvailability($id)
{
    try {
        $this->availabilityService->deleteAvailability((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Availability deleted successfully.',
        ]);

    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}


public function updateLocation(Request $request)
{
    $data = $request->validate([
        'latitude' => [
            'required',
            'numeric',
            'between:-90,90',
        ],

        'longitude' => [
            'required',
            'numeric',
            'between:-180,180',
        ],
    ]);

    $profile = $this->homeCareService->updateProviderLocation(
        $request->user(),
        $data
    );

    return response()->json([
        'success' => true,
        'message' => 'تم تحديث الموقع بنجاح.',
        'data' => $profile,
    ]);
}

}
