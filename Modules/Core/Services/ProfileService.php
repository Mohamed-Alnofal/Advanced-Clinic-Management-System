<?php 

namespace Modules\Core\Services;

use App\Support\PublicMedia;
use Modules\Core\Models\Pharmacist;
use Modules\Doctors\Models\Doctor;

class ProfileService
{
   
public function myProfile($user)
{
    $user->load(['doctor.certificates', 'pharmacist']);

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
        'age' => $user->age,
        'gender' => $user->gender,
        'status' => $user->status,
        'is_verified' => (bool) $user->is_verified,
        'suspension_reason' => $user->status === 'rejected' ? $user->suspension_reason : null,

        'role' => $user->getRoleNames()->first(),

        'doctor' => $user->doctor ? [
            'id' => $user->doctor->id,
            'bio' => $user->doctor->bio,
            'specialization_id' => $user->doctor->specialization_id,
            'price' => $user->doctor->price,
            'experience_years' => $user->doctor->experience_years,
            'is_verified' => (bool) $user->doctor->is_verified,
            'clinic_name' => $user->doctor->clinic_name,
            'clinic_address' => $user->doctor->clinic_address,
            'governorate_id' => $user->doctor->governorate_id,
            'application_status' => $user->doctor->is_verified
                ? 'approved'
                : (strtolower((string) ($user->status ?? 'pending'))),
            'rejection_reason' => $user->status === 'rejected'
                ? ($user->suspension_reason ?: null)
                : null,

            'image_url' => PublicMedia::url($user->doctor->image),

            'certificates' => $user->doctor->certificates->map(fn ($c) => [
                'id' => $c->id,
                'file_path' => $c->file_path,
                'url' => PublicMedia::url($c->file_path),
            ])->values(),

            'certificate_url' => PublicMedia::url(
                optional($user->doctor->certificates->first())->file_path
            ),
        ] : null,

        'pharmacist' => $user->pharmacist ? [
            'id' => $user->pharmacist->id,
            'pharmacy_name' => $user->pharmacist->pharmacy_name,
            'license_number' => $user->pharmacist->license_number,
            'is_verified' => (bool) $user->pharmacist->is_verified,

                'image_url' => PublicMedia::url($user->pharmacist->image),
            ] : null,
        ];
    }


    public function updateProfile($user, $request)
    {
        $user->update([

            'name' => $request->name ?? $user->name,

            'email' => $request->email ?? $user->email,

            'phone' => $request->phone ?? $user->phone,

            'date_of_birth' => $request->has('date_of_birth')
                ? $request->date_of_birth
                : $user->date_of_birth,

            'gender' => $request->gender ?? $user->gender,
        ]);

        // doctor
        if ($user->doctor) {

            $doctorData = [

                'bio' => $request->bio ?? $user->doctor->bio,

                'specialization_id' =>
                    $request->specialization_id
                    ?? $user->doctor->specialization_id,

                'price' => $request->price
                    ?? $user->doctor->price,

                'experience_years' =>
                    $request->experience_years
                    ?? $user->doctor->experience_years,
            ];

            if ($request->hasFile('image')) {
                $doctorData['image'] = PublicMedia::store(
                    $request->file('image'),
                    'doctors/profile'
                );
            }

            $user->doctor->update($doctorData);

            if ($request->hasFile('medical_certificate')) {
                $user->doctor->certificates()->create([
                    'file_path' => PublicMedia::store(
                        $request->file('medical_certificate'),
                        'doctors/certificates'
                    ),
                ]);
            }

            if ($request->hasFile('medical_certificates')) {
                $files = $request->file('medical_certificates');
                if (! is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    if ($file) {
                        $user->doctor->certificates()->create([
                            'file_path' => PublicMedia::store($file, 'doctors/certificates'),
                        ]);
                    }
                }
            }
        }

        // pharmacist
        if ($user->pharmacist) {

            $pharmacistData = [

                'pharmacy_name' =>
                    $request->pharmacy_name
                    ?? $user->pharmacist->pharmacy_name,

                'license_number' =>
                    $request->license_number
                    ?? $user->pharmacist->license_number,
            ];

            if ($request->hasFile('image')) {

                    $pharmacistData['image'] = PublicMedia::store(
                        $request->file('image'),
                        'pharmacists/profile'
                    );
            }

            $user->pharmacist->update($pharmacistData);
        }

        return $this->myProfile($user->fresh());
    }
    public function showDoctor($id)
        {
            $doctor = Doctor::with(['user', 'specialization', 'clinics.medicalCenter'])->findOrFail($id);
            // Public route, but Flutter sends Bearer — resolve JWT so can_rate / my_rating work.
            $user = $this->optionalApiUser();
            /** @var \Modules\Doctors\Services\DoctorReviewService $reviews */
            $reviews = app(\Modules\Doctors\Services\DoctorReviewService::class);
            $meta = $reviews->ratingMeta($doctor, $user);

            // Prefer all clinics for booking; only drop clearly rejected facilities.
            $clinics = $doctor->clinics
                ->filter(function ($clinic) {
                    $status = strtolower((string) ($clinic->medicalCenter?->status ?? 'approved'));

                    return $status !== 'rejected';
                })
                ->map(function ($clinic) {
                    $center = $clinic->medicalCenter;

                    return [
                        'id' => (int) $clinic->id,
                        'name' => $center?->name
                            ?? $center?->clinic_name
                            ?? ('Clinic #'.$clinic->id),
                        'address' => $center?->address
                            ?? $center?->location
                            ?? null,
                        'latitude' => $center?->latitude !== null ? (float) $center->latitude : null,
                        'longitude' => $center?->longitude !== null ? (float) $center->longitude : null,
                        'status' => $center?->status ?? 'approved',
                    ];
                })
                ->values()
                ->all();

            return [
                'id' => $doctor->id,
                'name' => $doctor->user->name,
                'bio' => $doctor->bio,
                'experience_years' => $doctor->experience_years,
                'price' => $doctor->price,

                'image_url' => PublicMedia::url($doctor->image),

                'clinic_address' => $doctor->clinic_address,
                'clinic_latitude' => $doctor->clinic_latitude,
                'clinic_longitude' => $doctor->clinic_longitude,
                'clinics' => $clinics,

                'average_rating' => $meta['average_rating'],
                'ratings_count' => $meta['ratings_count'],
                'rating_breakdown' => $meta['rating_breakdown'],
                'my_rating' => $meta['my_rating'],
                'can_rate' => $meta['can_rate'],
                'can_report' => $meta['can_rate'],
            ];
        }

    public function showPharmacist($id)
        {
        $pharma = Pharmacist::with('user')->findOrFail($id);

        return [
            'id' => $pharma->id,
            'name' => $pharma->user->name,
            'email' => $pharma->user->email,
            'phone' => $pharma->user->phone,

            'pharmacy_name' => $pharma->pharmacy_name,
            // 'license_number' => $pharma->license_number,

            // 🖼️ IMAGE URL
            'image_url' => PublicMedia::url($pharma->image),

            'clinic_address' => $pharma->clinic_address,
            // 'clinic_latitude' => $pharma->clinic_latitude,
            // 'clinic_longitude' => $pharma->clinic_longitude,
        ];
    }

    /**
     * Resolve JWT user on public routes when Authorization Bearer is present.
     */
    private function optionalApiUser()
    {
        try {
            if (! request()->bearerToken()) {
                return null;
            }

            return auth('api')->user()
                ?? \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return null;
        }
    }
}