<?php

namespace Modules\HomeCare\Services;
use Carbon\Carbon; 
use Modules\HomeCare\Models\HomeCareProfile;
use Modules\HomeCare\Models\HomeCareRequest;
use Modules\HomeCare\Models\HomeCareSpecialty;
use Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\HomeCare\Models\HomeCareProviderAvailability;  // ✅ أضف هذا السطر
class HomeCareService
{



/**
 * جلب جميع الأوقات المتاحة لمقدم الخدمة
 */
public function getAllAvailableSlots(
    HomeCareProfile $profile,
    string $period,
    string $date,
    int $slotDuration = 60
): array {
    
    return match($period) {
        'monthly' => $this->getMonthlyAvailableSlots($profile, $date, $slotDuration),
        'weekly' => $this->getWeeklyAvailableSlots($profile, $date, $slotDuration),
        'daily' => $this->getDailyAvailableSlots($profile, $date, $slotDuration),
        default => throw new \InvalidArgumentException('Invalid period type'),
    };
}

/**
 * جلب جميع الأوقات المتاحة في الشهر
 */
private function getMonthlyAvailableSlots(
    HomeCareProfile $profile,
    string $date,
    int $slotDuration = 60
): array {
    
    $month = Carbon::parse($date)->startOfMonth();
    $daysInMonth = $month->daysInMonth;
    $result = [];

    // جلب جميع التوفرات مرة واحدة لتحسين الأداء
    $allAvailabilities = HomeCareProviderAvailability::where(
        'home_care_profile_id',
        $profile->id
    )
    ->where('is_available', true)
    ->get();

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $currentDate = $month->copy()->day($day);
        
        // تخطي الأيام الماضية
        if ($currentDate->isPast()) {
            continue;
        }

        $dateString = $currentDate->format('Y-m-d');
        $dayOfWeek = strtolower($currentDate->format('l'));

        // جلب الأوقات المتاحة في هذا اليوم
        $slots = $this->getSlotsForDay(
            $profile,
            $dateString,
            $dayOfWeek,
            $allAvailabilities,
            $slotDuration
        );

        // جلب الأوقات المحجوزة
        $bookedSlots = $this->getBookedSlots($profile, $dateString);
        
        // إزالة الأوقات المحجوزة
        $freeSlots = array_diff($slots, $bookedSlots);

        if (!empty($freeSlots)) {
            $result[] = [
                'date' => $dateString,
                'day_name' => $currentDate->format('l'),
                'day_number' => $day,
                'is_available' => true,
                'slots' => array_values($freeSlots),
                'slots_count' => count($freeSlots),
                'booked_slots' => $bookedSlots,
            ];
        }
    }

    return [
        'period' => 'monthly',
        'month' => $month->format('F Y'),
        'available_days' => $result,
        'total_available_days' => count($result),
    ];
}

/**
 * جلب جميع الأوقات المتاحة في الأسبوع
 */
private function getWeeklyAvailableSlots(
    HomeCareProfile $profile,
    string $date,
    int $slotDuration = 60
): array {
    
    $startOfWeek = Carbon::parse($date)->startOfWeek();
    $endOfWeek = Carbon::parse($date)->endOfWeek();
    $result = [];

    $allAvailabilities = HomeCareProviderAvailability::where(
        'home_care_profile_id',
        $profile->id
    )
    ->where('is_available', true)
    ->get();

    $current = $startOfWeek->copy();
    while ($current <= $endOfWeek) {
        $dateString = $current->format('Y-m-d');
        $dayOfWeek = strtolower($current->format('l'));

        // تخطي الأيام الماضية
        if ($current->isPast()) {
            $current->addDay();
            continue;
        }

        $slots = $this->getSlotsForDay(
            $profile,
            $dateString,
            $dayOfWeek,
            $allAvailabilities,
            $slotDuration
        );

        $bookedSlots = $this->getBookedSlots($profile, $dateString);
        $freeSlots = array_diff($slots, $bookedSlots);

        if (!empty($freeSlots)) {
            $result[] = [
                'date' => $dateString,
                'day_name' => $current->format('l'),
                'is_available' => true,
                'slots' => array_values($freeSlots),
                'slots_count' => count($freeSlots),
                'booked_slots' => $bookedSlots,
            ];
        }

        $current->addDay();
    }

    return [
        'period' => 'weekly',
        'week_start' => $startOfWeek->format('Y-m-d'),
        'week_end' => $endOfWeek->format('Y-m-d'),
        'available_days' => $result,
        'total_available_days' => count($result),
    ];
}

/**
 * جلب جميع الأوقات المتاحة في يوم محدد
 */
private function getDailyAvailableSlots(
    HomeCareProfile $profile,
    string $date,
    int $slotDuration = 60
): array {
    
    $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
    $dateString = Carbon::parse($date)->format('Y-m-d');

    $allAvailabilities = HomeCareProviderAvailability::where(
        'home_care_profile_id',
        $profile->id
    )
    ->where('is_available', true)
    ->get();

    $slots = $this->getSlotsForDay(
        $profile,
        $dateString,
        $dayOfWeek,
        $allAvailabilities,
        $slotDuration
    );

    $bookedSlots = $this->getBookedSlots($profile, $dateString);
    $freeSlots = array_diff($slots, $bookedSlots);

    return [
        'period' => 'daily',
        'date' => $dateString,
        'day_name' => Carbon::parse($date)->format('l'),
        'is_available' => !empty($freeSlots),
        'slots' => array_values($freeSlots),
        'slots_count' => count($freeSlots),
        'booked_slots' => $bookedSlots,
        'all_slots' => $slots,
        'message' => empty($freeSlots) ? 'لا توجد أوقات متاحة في هذا اليوم' : null,
    ];
}

/**
 * جلب الأوقات المتاحة ليوم محدد
 */
private function getSlotsForDay(
    HomeCareProfile $profile,
    string $date,
    string $dayOfWeek,
    $availabilities,
    int $slotDuration = 60
): array {
    
    // 1. التحقق من التوفر اليومي (أولوية قصوى)
    $dailyAvailability = $availabilities
        ->where('type', 'daily')
        ->where('start_date', $date)
        ->first();

    if ($dailyAvailability) {
        return $this->generateTimeSlots(
            $dailyAvailability->start_time,
            $dailyAvailability->end_time,
            $slotDuration
        );
    }

    // 2. التحقق من التوفر الأسبوعي
    $weeklyAvailability = $availabilities
        ->where('type', 'weekly')
        ->where('day_of_week', $dayOfWeek)
        ->first();

    if ($weeklyAvailability) {
        return $this->generateTimeSlots(
            $weeklyAvailability->start_time,
            $weeklyAvailability->end_time,
            $slotDuration
        );
    }

    // 3. التحقق من التوفر الشهري
    $month = Carbon::parse($date)->format('Y-m');
    $monthlyAvailability = $availabilities
        ->where('type', 'monthly')
        ->where('start_date', '<=', $date)
        ->where('end_date', '>=', $date)
        ->first();

    if ($monthlyAvailability) {
        $excludedDates = $monthlyAvailability->excluded_dates ?? [];
        if (!in_array($date, $excludedDates)) {
            return $this->generateTimeSlots(
                $monthlyAvailability->start_time,
                $monthlyAvailability->end_time,
                $slotDuration
            );
        }
    }

    return [];
}

/**
 * جلب الأوقات المحجوزة لمقدم الخدمة في يوم محدد
 */
public function getBookedSlots(
    HomeCareProfile $profile,
    string $date
): array {
    
    $bookedRequests = HomeCareRequest::where(
        'home_care_profile_id',
        $profile->id
    )
    ->where('requested_date', $date)
    ->whereIn('status', ['pending', 'accepted'])
    ->get();

    return $bookedRequests
        ->pluck('requested_time')
        ->map(function($time) {
            return Carbon::parse($time)->format('H:i');
        })
        ->toArray();
}

/**
 * إنشاء فترات زمنية بين وقت البدء والانتهاء
 */
private function generateTimeSlots(
    string $startTime,
    string $endTime,
    int $duration = 60
): array {
    $slots = [];
    $start = Carbon::parse($startTime);
    $end = Carbon::parse($endTime);

    while ($start < $end) {
        $slots[] = $start->format('H:i');
        $start->addMinutes($duration);
    }

    return $slots;
}

/**
 * التحقق من توفر وقت محدد قبل الحجز
 */
public function checkSlotAvailability(
    HomeCareProfile $profile,
    string $date,
    string $time
): bool {
    
    $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
    
    // جلب جميع الأوقات المتاحة في هذا اليوم
    $allAvailabilities = HomeCareProviderAvailability::where(
        'home_care_profile_id',
        $profile->id
    )
    ->where('is_available', true)
    ->get();

    $availableSlots = $this->getSlotsForDay(
        $profile,
        $date,
        $dayOfWeek,
        $allAvailabilities,
        60
    );

    // التحقق من أن الوقت ضمن الأوقات المتاحة
    if (!in_array($time, $availableSlots)) {
        return false;
    }

    // التحقق من أن الوقت غير محجوز
    $bookedSlots = $this->getBookedSlots($profile, $date);
    if (in_array($time, $bookedSlots)) {
        return false;
    }

    return true;
}

/**
 * تحديث الملف الشخصي لمقدم الرعاية المنزلية
 */
public function updateProfile(User $user, array $data): HomeCareProfile
{
    $profile = HomeCareProfile::where('user_id', $user->id)->firstOrFail();

    // ✅ تحديث الحقول المطلوبة فقط
    $updateData = [];

    if (isset($data['provider_type'])) {
        $updateData['provider_type'] = $data['provider_type'];
    }

    if (isset($data['full_name'])) {
        $updateData['full_name'] = $data['full_name'];
    }

    if (isset($data['license_number'])) {
        $updateData['license_number'] = $data['license_number'];
    }

    if (isset($data['bio'])) {
        $updateData['bio'] = $data['bio'];
    }

    if (isset($data['experience_years'])) {
        $updateData['experience_years'] = (int) $data['experience_years'];
    }

    if (isset($data['visit_fee'])) {
        $updateData['visit_fee'] = (float) $data['visit_fee'];
    }

    if (isset($data['service_radius_km'])) {
        $updateData['service_radius_km'] = (float) $data['service_radius_km'];
    }

    if (isset($data['latitude'])) {
        $updateData['latitude'] = (float) $data['latitude'];
        $updateData['location_updated_at'] = now();
    }

    if (isset($data['longitude'])) {
        $updateData['longitude'] = (float) $data['longitude'];
        $updateData['location_updated_at'] = now();
    }

    if (isset($data['notes'])) {
        $updateData['notes'] = $data['notes'];
    }

    if (isset($data['available_days'])) {
        $updateData['available_days'] = $data['available_days'];
    }

    if (isset($data['available_hours'])) {
        $updateData['available_hours'] = $data['available_hours'];
    }

    // ✅ تحديث الحالة فقط إذا لم تكن معتمدة
    if ($profile->status !== 'approved') {
        $updateData['status'] = 'pending';
    }

    $profile->update($updateData);

    return $profile->fresh(['specialty', 'doctor.user']);
}
/**
 * Find providers that serve the patient's location.
 */
public function nearbyProviders(
    float $latitude,
    float $longitude,
    ?int $specialtyId = null
) {
    $query = HomeCareProfile::with([
        'user',
        'doctor.user',
        'specialty',
        'availability',
    ])
        ->where('status', 'approved')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->where('service_radius_km', '>', 0);

    if ($specialtyId) {
        $query->where(
            'specialty_id',
            $specialtyId
        );
    }

    $providers = $query->get();

    return $providers
        ->map(function ($provider) use (
            $latitude,
            $longitude
        ) {

            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                (float) $provider->latitude,
                (float) $provider->longitude
            );

            if (
                $distance > (float) $provider->service_radius_km
            ) {
                return null;
            }

            $provider->distance_km = round(
                $distance,
                2
            );

            return $provider;
        })
        ->filter()
        ->sortBy('distance_km')
        ->values();
}
    /**
     * Get active home care specialties.
     */
    public function specialties()
    {
        return HomeCareSpecialty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get approved home care providers visible to patients.
     */
    public function approvedProfiles(?User $viewer = null)
    {
        $query = HomeCareProfile::with([
            'user',
            'doctor.user',
            'specialty',
            'availability',
        ])
            ->where('status', 'approved');

        if ($viewer) {
            $query->where('user_id', '!=', $viewer->id);
        }

        return $query
            ->orderBy('full_name')
            ->get();
    }

    public function profileForUser(User $user): ?HomeCareProfile
    {
        return HomeCareProfile::with(['specialty', 'doctor.user', 'availability'])
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Create or update home care provider profile.
     */
public function storeProfile(User $user, array $data): HomeCareProfile
{
    $doctor = $user->doctor;
    
    // ✅ الحصول على الملف الشخصي أو إنشاء جديد
    $profile = HomeCareProfile::firstOrNew(['user_id' => $user->id]);
    
    // ✅ تعيين القيم من البيانات الجديدة (تجاوز القيم القديمة)
    $profile->doctor_id = $doctor?->id;
    $profile->specialty_id = $data['specialty_id'] ?? null;
    $profile->provider_type = $data['provider_type'] ?? 'general';
    $profile->full_name = $data['full_name'] ?? $user->name;
    $profile->license_number = $data['license_number'] ?? null;
    $profile->bio = $data['bio'] ?? null;
    $profile->experience_years = isset($data['experience_years']) ? (int) $data['experience_years'] : 0;
    $profile->visit_fee = isset($data['visit_fee']) ? (float) $data['visit_fee'] : 0;
    $profile->service_radius_km = isset($data['service_radius_km']) ? (float) $data['service_radius_km'] : 0;
    $profile->available_days = $data['available_days'] ?? null;
    $profile->available_hours = $data['available_hours'] ?? null;
    $profile->notes = $data['notes'] ?? null;
    $profile->latitude = $data['latitude'] ?? null;
    $profile->longitude = $data['longitude'] ?? null;
    
    // ✅ معالجة الشهادة
    if (!empty($data['certificate_path'])) {
        $profile->certificate_path = $data['certificate_path'];
    }
    
    // ✅ تحديث الحالة (إذا كان معتمداً مسبقاً، ابقِه معتمداً)
    if ($profile->exists && $profile->status === 'approved') {
        $profile->status = 'approved';
    } else {
        $profile->status = 'pending';
    }
    
    $profile->save();
    
    return $profile->fresh(['specialty', 'doctor.user']);
}

    /**
     * Create a home care request.
     *
     * NOTE:
     * Availability and schedule-conflict validation
     * will be handled separately.
     */
   public function createRequest(
    User $user,
    array $data
): HomeCareRequest {

    /*
    |--------------------------------------------------------------------------
    | Prevent provider from requesting home care
    |--------------------------------------------------------------------------
    */

    $ownProfile = HomeCareProfile::where(
        'user_id',
        $user->id
    )->first();

    if (
        $ownProfile &&
        $ownProfile->status === 'approved'
    ) {
        throw ValidationException::withMessages([
            'home_care' =>
                'لا يمكن لمقدم خدمة الرعاية المنزلية طلب خدمة رعاية لنفسه.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get provider
    |--------------------------------------------------------------------------
    */

    $profile = HomeCareProfile::with([
        'specialty',
    ])->findOrFail(
        $data['home_care_profile_id']
    );

    /*
    |--------------------------------------------------------------------------
    | Prevent requesting yourself
    |--------------------------------------------------------------------------
    */

    if (
        (int) $profile->user_id ===
        (int) $user->id
    ) {
        throw ValidationException::withMessages([
            'home_care_profile_id' =>
                'لا يمكنك طلب الرعاية من حسابك الخاص.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Provider approval
    |--------------------------------------------------------------------------
    */

    if ($profile->status !== 'approved') {

        throw ValidationException::withMessages([
            'provider' =>
                'مقدم الخدمة غير معتمد حالياً.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Check patient location
    |--------------------------------------------------------------------------
    */

    if (
        ! isset($data['latitude']) ||
        ! isset($data['longitude'])
    ) {
        throw ValidationException::withMessages([
            'location' =>
                'يجب تحديد موقعك لطلب خدمة الرعاية المنزلية.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Check provider location
    |--------------------------------------------------------------------------
    */

    if (
        $profile->latitude === null ||
        $profile->longitude === null
    ) {
        throw ValidationException::withMessages([
            'provider_location' =>
                'موقع مقدم الخدمة غير متاح حالياً.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate distance
    |--------------------------------------------------------------------------
    */

    $distance = $this->calculateDistance(
        (float) $data['latitude'],
        (float) $data['longitude'],
        (float) $profile->latitude,
        (float) $profile->longitude
    );

    /*
    |--------------------------------------------------------------------------
    | Check provider service radius
    |--------------------------------------------------------------------------
    */

    $serviceRadius =
        (float) $profile->service_radius_km;

    if (
        $serviceRadius > 0 &&
        $distance > $serviceRadius
    ) {
        throw ValidationException::withMessages([
            'distance' =>
                'أنت خارج نطاق خدمة مقدم الرعاية. المسافة الحالية هي '
                . round($distance, 2)
                . ' كم، بينما الحد الأقصى لمقدم الخدمة هو '
                . $serviceRadius
                . ' كم.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize date / time and validate the chosen slot
    |--------------------------------------------------------------------------
    */

    $date = Carbon::parse($data['requested_date'])->toDateString();
    $time = Carbon::parse($data['requested_time'])->format('H:i');
    $data['requested_date'] = $date;
    $data['requested_time'] = $time;

    if (! $this->checkSlotAvailability($profile, $date, $time)) {
        throw ValidationException::withMessages([
            'requested_time' =>
                'هذا الوقت غير متاح. اختر وقتاً من الأوقات المعروضة.',
        ]);
    }

    $alreadyBooked = HomeCareRequest::query()
        ->where('patient_id', $user->id)
        ->where('home_care_profile_id', $profile->id)
        ->whereDate('requested_date', $date)
        ->whereIn('status', ['pending', 'accepted'])
        ->get()
        ->contains(function ($request) use ($time) {
            return Carbon::parse($request->requested_time)->format('H:i') === $time;
        });

    if ($alreadyBooked) {
        throw ValidationException::withMessages([
            'requested_time' =>
                'لديك طلب سابق على نفس الوقت. لا يمكن الحجز مرتين.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create request
    |--------------------------------------------------------------------------
    */

    return HomeCareRequest::create([
        'patient_id' =>
            $user->id,

        'home_care_profile_id' =>
            $profile->id,

        'specialty_id' =>
            $data['specialty_id']
            ?? $profile->specialty_id,

        'patient_name' =>
            $data['patient_name']
            ?? $user->name,

        'requested_date' =>
            $data['requested_date'],

        'requested_time' =>
            $data['requested_time'],

        'latitude' =>
            $data['latitude'],

        'longitude' =>
            $data['longitude'],

        'address' =>
            $data['address'] ?? null,

        'notes' =>
            $data['notes'] ?? null,

        'status' =>
            'pending',

        'visit_fee' =>
            $profile->visit_fee,
    ]);
}

    /**
     * Get provider home care requests.
     */
    public function patientRequests(User $user)
    {
        return HomeCareRequest::with(['provider.specialty', 'provider.user', 'specialty'])
            ->where('patient_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function providerRequests(User $user)
    {
        $profile = HomeCareProfile::where('user_id', $user->id)->firstOrFail();

        return HomeCareRequest::with(['patient', 'specialty'])
            ->where('home_care_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Update home care request status.
     */
    public function updateRequestStatus(
        User $user,
        int $requestId,
        array $data
    ): HomeCareRequest {

        $profile = HomeCareProfile::where(
            'user_id',
            $user->id
        )->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Find request belonging to provider
        |--------------------------------------------------------------------------
        */

        $request = HomeCareRequest::where(
            'id',
            $requestId
        )
            ->where(
                'home_care_profile_id',
                $profile->id
            )
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Update status
        |--------------------------------------------------------------------------
        */

        $request->update([
            'status' =>
                $data['status'],

            'reject_reason' =>
                $data['status'] === 'rejected'
                    ? ($data['reject_reason'] ?? null)
                    : null,

            'cancel_reason' =>
                $data['status'] === 'cancelled'
                    ? ($data['cancel_reason'] ?? null)
                    : null,

            'completed_at' =>
                $data['status'] === 'completed'
                    ? now()
                    : null,
        ]);

        return $request->fresh();
    }


    /**
 * Update current location of the authenticated home care provider.
 */
public function updateProviderLocation(
    User $user,
    array $data
): HomeCareProfile {

    $profile = HomeCareProfile::where(
        'user_id',
        $user->id
    )->firstOrFail();

    /*
    |--------------------------------------------------------------------------
    | Update current location
    |--------------------------------------------------------------------------
    */

    $profile->update([
        'latitude' => $data['latitude'],
        'longitude' => $data['longitude'],
        'location_updated_at' => now(),
    ]);

    return $profile->fresh([
        'user',
        'specialty',
        'doctor.user',
    ]);
}

/**
 * Calculate distance between two geographic points in kilometers.
 */
/**
 * Calculate distance between two coordinates in kilometers.
 */
private function calculateDistance(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
): float {

    $earthRadius = 6371;

    $latitudeFrom = deg2rad($latitude1);
    $longitudeFrom = deg2rad($longitude1);

    $latitudeTo = deg2rad($latitude2);
    $longitudeTo = deg2rad($longitude2);

    $latitudeDelta =
        $latitudeTo - $latitudeFrom;

    $longitudeDelta =
        $longitudeTo - $longitudeFrom;

    $angle =
        sin($latitudeDelta / 2) *
        sin($latitudeDelta / 2)
        +
        cos($latitudeFrom) *
        cos($latitudeTo) *
        sin($longitudeDelta / 2) *
        sin($longitudeDelta / 2);

    $centralAngle =
        2 * atan2(
            sqrt($angle),
            sqrt(1 - $angle)
        );

    return $earthRadius * $centralAngle;
}}