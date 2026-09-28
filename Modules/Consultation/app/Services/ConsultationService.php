<?php

namespace Modules\Consultation\Services;

use Modules\Consultation\Models\Consultation;
use Modules\Consultation\Models\VideoAudit;
use Modules\Consultation\Models\VideoSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConsultationService
{
    /**
     * الحصول على جميع الاستشارات مع Pagination
     */
    public function getConsultations($perPage = 15, $filters = [])
    {
        $cacheKey = 'admin_consultations_' . md5(json_encode($filters) . $perPage);

        return Cache::remember($cacheKey, 3600, function () use ($perPage, $filters) {
            $query = Consultation::with(['appointment.patient', 'appointment.doctor']);

            // تطبيق الفلاتر
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['rating_min'])) {
                $query->where('rating', '>=', $filters['rating_min']);
            }

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        });
    }

    /**
     * الحصول على استشارة محددة مع Cache
     */
    public function getConsultation($id)
    {
        $cacheKey = "consultation_{$id}";

        return Cache::remember($cacheKey, 3600, function () use ($id) {
            return Consultation::with([
                'appointment.patient',
                'appointment.doctor',
                'videoAudits'
            ])->findOrFail($id);
        });
    }

    /**
     * إلغاء استشارة
     */
    public function cancelConsultation($id, $adminId, $adminName)
    {
        $consultation = Consultation::findOrFail($id);

        if (in_array($consultation->status, ['completed', 'cancelled'])) {
            throw new \Exception('This consultation has already ended');
        }

        $consultation->update([
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        $this->logVideoAudit($consultation, 'admin_cancelled', [
            'admin_id' => $adminId,
            'admin_name' => $adminName,
        ]);

        // مسح الكاش
        Cache::forget("consultation_{$id}");
        Cache::forget('admin_consultations_*');

        return $consultation;
    }

    /**
     * حذف استشارة
     */
    public function deleteConsultation($id)
    {
        $consultation = Consultation::findOrFail($id);
        VideoAudit::where('consultation_id', $id)->delete();
        $consultation->delete();

        // مسح الكاش
        Cache::forget("consultation_{$id}");
        Cache::forget('admin_consultations_*');

        return true;
    }

    /**
     * الحصول على سجلات المكالمات مع Pagination
     */
    public function getAudits($perPage = 20, $filters = [])
    {
        $cacheKey = 'admin_audits_' . md5(json_encode($filters) . $perPage);

        return Cache::remember($cacheKey, 3600, function () use ($perPage, $filters) {
            $query = VideoAudit::with(['user', 'consultation']);

            if (!empty($filters['event'])) {
                $query->where('event', $filters['event']);
            }

            if (!empty($filters['user_role'])) {
                $query->where('user_role', $filters['user_role']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            return $query->orderBy('created_at', 'desc')->paginate($perPage);
        });
    }

    /**
     * الحصول على سجلات مكالمة محددة
     */
    public function getConsultationAudits($consultationId)
    {
        $cacheKey = "consultation_audits_{$consultationId}";

        return Cache::remember($cacheKey, 3600, function () use ($consultationId) {
            return VideoAudit::where('consultation_id', $consultationId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();
        });
    }

    /**
     * الحصول على إحصائيات المكالمات
     */
    public function getStats($startDate, $endDate)
    {
        $cacheKey = "consultation_stats_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            $consultations = Consultation::whereBetween('created_at', [$startDate, $endDate]);

            return [
                'total' => Consultation::count(),
                'active' => Consultation::where('status', 'active')->count(),
                'completed' => Consultation::where('status', 'completed')->count(),
                'cancelled' => Consultation::where('status', 'cancelled')->count(),
                'period' => [
                    'total' => $consultations->count(),
                    'completed' => (clone $consultations)->where('status', 'completed')->count(),
                ],
                'avg_duration' => Consultation::whereNotNull('started_at')
                    ->whereNotNull('ended_at')
                    ->avg(fn($c) => $c->started_at->diffInSeconds($c->ended_at)),
                'participants' => [
                    'doctors' => Consultation::with('appointment.doctor')->get()
                        ->pluck('appointment.doctor_id')->unique()->count(),
                    'patients' => Consultation::with('appointment.patient')->get()
                        ->pluck('appointment.patient_id')->unique()->count(),
                ],
            ];
        });
    }

    /**
     * الحصول على المشتركين مع Pagination
     */
    public function getSubscribers($perPage = 20)
    {
        $cacheKey = "admin_subscribers_{$perPage}";

        return Cache::remember($cacheKey, 3600, function () use ($perPage) {
            return VideoSubscription::with(['user', 'doctor'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        });
    }

    /**
     * الحصول على مشتركي طبيب معين
     */
    public function getDoctorSubscribers($doctorId)
    {
        $cacheKey = "doctor_subscribers_{$doctorId}";

        return Cache::remember($cacheKey, 3600, function () use ($doctorId) {
            return VideoSubscription::where('doctor_id', $doctorId)
                ->with('user')
                ->get();
        });
    }

    /**
     * تسجيل أحداث الفيديو
     */
    private function logVideoAudit($consultation, $event, $details = [])
    {
        try {
            VideoAudit::create([
                'consultation_id' => $consultation->id,
                'appointment_id' => $consultation->appointment_id,
                'user_id' => auth()->id(),
                'user_role' => 'admin',
                'user_name' => auth()->user()->name,
                'user_email' => auth()->user()->email,
                'event' => $event,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * مسح الكاش المرتبط بالاستشارات
     */
    public function clearCache()
    {
        Cache::forget('admin_consultations_*');
        Cache::forget('admin_audits_*');
        Cache::forget('consultation_stats_*');
        Cache::forget('admin_subscribers_*');
        Cache::flush();
    }
}