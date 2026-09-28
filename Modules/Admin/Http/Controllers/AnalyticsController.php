<?php

namespace Modules\Admin\Http\Controllers;
use Modules\Core\Http\Controllers\Controller;

use Modules\Core\Services\AnalyticsService;
class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * عرض جميع التحليلات
     */
    public function index()
    {
        $analytics = $this->analyticsService->getAllAnalytics();
        
        return response()->json([
            'status' => true,
            'data' => $analytics
        ]);
    }

    /**
     * عرض إحصائيات المستخدمين فقط
     */
    public function userStats()
    {
        return response()->json([
            'status' => true,
            'data' => $this->analyticsService->getUserStats()
        ]);
    }

    /**
     * عرض إحصائيات الأطباء والتخصصات
     */
    public function doctorStats()
    {
        return response()->json([
            'status' => true,
            'data' => $this->analyticsService->getDoctorStats()
        ]);
    }

    /**
     * عرض إحصائيات المواعيد ومعدل الإلغاء
     */
    // public function appointmentStats()
    // {
    //     return response()->json([
    //         'status' => true,
    //         'data' => $this->analyticsService->getAppointmentStats()
    //     ]);
    // }

    /**
     * عرض إحصائيات النشاط
     */
    public function activityStats()
    {
        return response()->json([
            'status' => true,
            'data' => $this->analyticsService->getActivityStats()
        ]);
    }

    public function dashboard()
{
    return response()->json([
        'status' => true,
        'data' => $this->analyticsService->getDashboardStats()
    ]);
}
}