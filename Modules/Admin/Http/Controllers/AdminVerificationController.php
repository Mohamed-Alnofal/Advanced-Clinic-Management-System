<?php

namespace Modules\Admin\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Modules\Core\Support\AdminStatsCache;
use Modules\Doctors\Models\Doctor;
use Modules\Pharmacist\Models\Pharmacist;
use Illuminate\Http\Request;
use Modules\Users\Events\UserRejected;
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\Ambulance\Models\AmbulanceCompany;
use Modules\Users\Models\User;
use App\Support\PublicMedia;
class AdminVerificationController extends Controller
{
    public function __construct()
    {
        
    }

    // عرض الأطباء غير الموثقين
    public function unverifiedDoctors()
    {
        $doctors = Doctor::with(['user', 'specialization', 'certificates', 'governorate'])
            ->where('is_verified', false)
            ->latest()
            ->get()
            ->map(function (Doctor $doctor) {
                $doctor->makeVisible(['created_at', 'updated_at']);

                $doctor->setAttribute(
                    'image_url',
                    PublicMedia::url($doctor->image)
                );

                $doctor->setRelation(
                    'certificates',
                    $doctor->certificates->map(function ($cert) {
                        $cert->setAttribute(
                            'url',
                            PublicMedia::url($cert->file_path)
                        );
                        return $cert;
                    })
                );

                if ($doctor->governorate) {
                    $doctor->setAttribute('governorate_name', $doctor->governorate->name);
                }

                $doctor->setAttribute(
                    'application_status',
                    strtolower((string) ($doctor->user?->status ?? 'pending'))
                );
                $doctor->setAttribute(
                    'rejection_reason',
                    $doctor->user?->status === 'rejected'
                        ? ($doctor->user->suspension_reason ?: null)
                        : null
                );

                return $doctor;
            });

        return response()->json([
            'doctors' => $doctors,
        ]);
    }

    // عرض الصيادلة غير الموثقين
    public function unverifiedPharmacists()
    {
        $pharmacists = Pharmacist::with('user')
            ->where('is_verified', false)
            ->get();

        return response()->json([
            'pharmacists' => $pharmacists
        ]);
    }

    // الموافقة على طبيب
    public function approveDoctor($id)
    {
        $doctor = Doctor::with('user')->findOrFail($id);
        $doctor->update(['is_verified' => true]);

        $doctor->user->update([
            'is_verified' => true,
            'status' => 'accepted',
            'suspension_reason' => null,
        ]);

        AdminStatsCache::forgetUserLifecycleStats();

        return response()->json([
            'message' => 'Doctor approved successfully'
        ]);
    }

    // الموافقة على صيدلي
    public function approvePharmacist($id)
    {
        $pharmacist = Pharmacist::findOrFail($id);
        $pharmacist->update(['is_verified' => true]);
        
        // $pharmacist->user->update(['is_verified' => true]);
             $pharmacist->user->update([
            'is_verified' => true,
            'status' => 'accepted'
        ]);

        AdminStatsCache::forgetUserLifecycleStats();

        return response()->json([
            'message' => 'Pharmacist approved successfully'
        ]);
    }

public function rejectDoctor(Request $request, $id)
{
    $request->validate([
        'reason' => 'required|string|min:3|max:2000',
    ]);

    $doctor = Doctor::with('user')->findOrFail($id);

    $reason = trim((string) $request->reason);

    event(new UserRejected($doctor->user, $reason));

    $doctor->update(['is_verified' => false]);

    // Keep users.is_verified (email OTP). Only mark admin review as rejected.
    $doctor->user->update([
        'status' => 'rejected',
        'suspension_reason' => $reason,
    ]);

    AdminStatsCache::forgetUserLifecycleStats();

    return response()->json([
        'message' => 'Doctor rejected successfully',
        'reason' => $reason,
    ]);
}


    public function rejectPharmacist(Request $request, $id)
{
    $pharmacist = Pharmacist::findOrFail($id);

    $reason = $request->reason;

    event(new UserRejected($pharmacist->user, $reason));

    $pharmacist->update(['is_verified' => false]);

        $pharmacist->user->update([
        'status' => 'rejected',
        'suspension_reason' => $reason,
        // Keep email verified so rejected pharmacists can sign in and re-upload.
        'is_verified' => true,
    ]);

    AdminStatsCache::forgetUserLifecycleStats();

    return response()->json([
        'message' => 'Pharmacist rejected successfully',
        'reason' => $reason
    ]);
}


            public function pendingStats()
        {
            $pendingDoctors = 0;
            $pendingPharmacists = 0;
            $pendingClinics = 0;
            $pendingPharmacies = 0;

            try {
                $pendingDoctors = Doctor::where('is_verified', false)->count();
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $pendingPharmacists = Pharmacist::where('is_verified', false)->count();
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $pendingClinics = MedicalFacility::query()
                    ->where('type', 'clinic')
                    ->where('status', 'pending')
                    ->count();
            } catch (\Throwable $e) {
                report($e);
            }

            try {
                $pendingPharmacies = MedicalFacility::query()
                    ->where('type', 'pharmacy')
                    ->where('status', 'pending')
                    ->count();
            } catch (\Throwable $e) {
                report($e);
            }

            return response()->json([
                'pending_doctors' => $pendingDoctors,
                'pending_pharmacists' => $pendingPharmacists,
                'pending_clinics' => $pendingClinics,
                'pending_pharmacies' => $pendingPharmacies,
                'total_pending' => $pendingDoctors + $pendingPharmacists + $pendingClinics + $pendingPharmacies,
                'total_provider_pending' => $pendingDoctors + $pendingPharmacists,
                'total_facility_pending' => $pendingClinics + $pendingPharmacies,
            ]);
        }



                    public function pendingAmbulanceUsers()
            {
                try {
                    \Spatie\Permission\Models\Role::findOrCreate('ambulance', 'api');

                    // Avoid Spatie role() scope throwing RoleDoesNotExist on empty DBs.
                    $roleId = \Spatie\Permission\Models\Role::query()
                        ->where('name', 'ambulance')
                        ->where('guard_name', 'api')
                        ->value('id');

                    if (! $roleId) {
                        return response()->json(['ambulance_users' => []]);
                    }

                    $userIds = \Illuminate\Support\Facades\DB::table('model_has_roles')
                        ->where('role_id', $roleId)
                        ->where('model_type', User::class)
                        ->pluck('model_id');

                    $users = User::query()
                        ->whereIn('id', $userIds)
                        ->where(function ($q) {
                            $q->where('status', 'pending')
                                ->orWhere(function ($inner) {
                                    $inner->where('is_verified', false)
                                        ->where(function ($s) {
                                            $s->whereNull('status')
                                                ->orWhereNotIn('status', ['accepted', 'rejected', 'suspended']);
                                        });
                                });
                        })
                        ->with('roles')
                        ->latest()
                        ->get();
                } catch (\Throwable $e) {
                    report($e);

                    return response()->json([
                        'message' => 'Could not load ambulance operators.',
                        'ambulance_users' => [],
                        'error' => config('app.debug') ? $e->getMessage() : null,
                    ], 200);
                }

                return response()->json([
                    'ambulance_users' => $users,
                ]);
            }

                public function ambulanceUserAction(Request $request, $id)
        {
            $request->validate([
                'action' => 'required|in:approve,reject',
            ]);

            $user = User::findOrFail($id);

            if (!$user->hasRole('ambulance')) {
                return response()->json([
                    'message' => 'This account is not an ambulance account.'
                ], 400);
            }

            if ($request->action === 'approve') {

                $user->update([
                    'is_verified' => true,
                    'status' => 'accepted',
                    'suspension_reason' => null,
                ]);

                AdminStatsCache::forgetUserLifecycleStats();

                return response()->json([
                    'message' => 'Ambulance account approved successfully.'
                ]);
            }

            $reason = trim((string) $request->input('reason', 'Rejected by admin'));

            $user->update([
                'is_verified' => false,
                'status' => 'rejected',
                'suspension_reason' => $reason !== '' ? $reason : 'Rejected by admin',
            ]);

            AdminStatsCache::forgetUserLifecycleStats();

            return response()->json([
                'message' => 'Ambulance account rejected successfully.'
            ]);
        }


            public function pendingAmbulanceCompanies()
            {
                $companies = AmbulanceCompany::with('user')
                    ->where('status', 'pending')
                    ->latest()
                    ->get();

                return response()->json([
                    'companies' => $companies
                ]);
            }

            public function ambulanceCompanyAction(Request $request, $id)
            {
                $request->validate([
                    'action' => 'required|in:approve,reject',
                ]);

                $company = AmbulanceCompany::findOrFail($id);

                $company->update([
                    'status' => $request->action === 'approve'
                        ? 'approved'
                        : 'rejected',
                ]);

                return response()->json([
                    'message' => 'Company status updated successfully.',
                    'status'  => $company->status,
                ]);
            }
}