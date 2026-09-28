<?php

namespace Modules\HomeCare\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\HomeCare\Models\HomeCareProfile;
use Modules\HomeCare\Models\HomeCareRequest;
use Modules\HomeCare\Models\HomeCareSpecialty;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class AdminHomeCareController extends Controller
{
    /**
     * عرض طلبات التسجيل المعلقة
     * GET /api/admin/home-care/pending
     */
    public function pendingProfiles()
    {
        $profiles = HomeCareProfile::with(['user', 'specialty'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $profiles,
        ]);
    }

    /**
     * قبول طلب تسجيل مقدم خدمة
     * PUT /api/admin/home-care/approve/{id}
     */
    public function approveProfile($id, Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $profile = HomeCareProfile::findOrFail($id);

        if ($profile->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'هذا الطلب تمت الموافقة عليه مسبقاً',
            ], 422);
        }

        // تحديث حالة الملف الشخصي
        $profile->update([
            'status' => 'approved',
            'approved_at' => Carbon::now(),
            'notes' => $request->notes ?? $profile->notes,
        ]);

        // Keep the original patient/doctor role. Home care is an extra profile.
        $user = $profile->user;
        if ($user && ! $user->hasRole('provider')) {
            Role::findOrCreate('provider', 'api');
            $user->assignRole('provider');
        }

        return response()->json([
            'success' => true,
            'message' => 'تم قبول طلب مقدم الخدمة بنجاح',
            'data' => $profile->fresh(['user', 'specialty']),
        ]);
    }

    /**
     * رفض طلب تسجيل مقدم خدمة
     * PUT /api/admin/home-care/reject/{id}
     */
    public function rejectProfile($id, Request $request)
    {
        $request->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        $profile = HomeCareProfile::findOrFail($id);

        if ($profile->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفض طلب تمت الموافقة عليه مسبقاً',
            ], 422);
        }

        $profile->update([
            'status' => 'suspended',
            'notes' => $request->reject_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض طلب مقدم الخدمة',
            'data' => $profile->fresh(['user', 'specialty']),
        ]);
    }

    /**
     * عرض جميع مقدمي الخدمة
     * GET /api/admin/home-care/providers
     */
    public function allProviders(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $providers = HomeCareProfile::with(['user', 'specialty'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $providers,
        ]);
    }

    /**
     * عرض مقدم خدمة محدد
     * GET /api/admin/home-care/providers/{id}
     */
    public function showProvider($id)
    {
        $provider = HomeCareProfile::with(['user', 'specialty', 'requests'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $provider,
        ]);
    }

    /**
     * إحصائيات الرعاية المنزلية
     * GET /api/admin/home-care/stats
     */
    public function stats(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());

        $stats = [
            'providers' => [
                'total' => HomeCareProfile::count(),
                'pending' => HomeCareProfile::where('status', 'pending')->count(),
                'approved' => HomeCareProfile::where('status', 'approved')->count(),
                'suspended' => HomeCareProfile::where('status', 'suspended')->count(),
            ],
            'requests' => [
                'total' => HomeCareRequest::count(),
                'pending' => HomeCareRequest::where('status', 'pending')->count(),
                'accepted' => HomeCareRequest::where('status', 'accepted')->count(),
                'completed' => HomeCareRequest::where('status', 'completed')->count(),
                'rejected' => HomeCareRequest::where('status', 'rejected')->count(),
                'cancelled' => HomeCareRequest::where('status', 'cancelled')->count(),
            ],
            'specialties' => HomeCareSpecialty::where('is_active', true)->count(),
            'period' => [
                'new_providers' => HomeCareProfile::whereBetween('created_at', [$startDate, $endDate])->count(),
                'new_requests' => HomeCareRequest::whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}