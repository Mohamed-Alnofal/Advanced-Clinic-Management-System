<?php

namespace App\Repositories;

use Modules\Pharmacy\Models\Pharmacy;
use Modules\MedicalFacility\Models\MedicalFacility;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PharmacyRepository
{
    /**
     * الصيدليات القريبة (ضمن 10 كم)
     */
    public function getNearby($lat, $lng, $limit = 20)
    {
        $cacheKey = "nearby_pharmacies_{$lat}_{$lng}";
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($lat, $lng, $limit) {
            return MedicalFacility::where('type', 'pharmacy')
                ->where('is_active', true)
                ->selectRaw("
                    *,
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )) as distance
                ", [$lat, $lng, $lat])
                ->having('distance', '<', 10)
                ->orderBy('distance')
                ->limit($limit)
                ->get();
        });
    }
    
    /**
     * الصيدليات التي يتوفر فيها دواء معين
     */
    public function getByDrug($drugId, $lat, $lng)
    {
        $cacheKey = "pharmacies_by_drug_{$drugId}_{$lat}_{$lng}";
        
        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($drugId, $lat, $lng) {
            return Pharmacy::with(['medicalFacility', 'schedules', 'drugs'])
                ->whereHas('medicalFacility', function ($q) {
                    $q->where('type', 'pharmacy')->where('is_active', true);
                })
                ->whereHas('drugs', function ($q) use ($drugId) {
                    $q->where('drug_id', $drugId)->where('quantity', '>', 0);
                })
                ->get()
                ->map(function ($pharmacy) use ($lat, $lng) {
                    // حساب المسافة
                    $pharmacy->distance = $this->calculateDistance(
                        $lat, 
                        $lng, 
                        $pharmacy->medicalFacility->latitude, 
                        $pharmacy->medicalFacility->longitude
                    );
                    $pharmacy->is_open = $this->isOpenNow($pharmacy->schedules);
                    return $pharmacy;
                })
                ->sortBy('distance')
                ->values();
        });
    }
    
    /**
     * حساب المسافة بين نقطتين (كم)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        if (!$lat2 || !$lng2) return null;
        
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return round($earthRadius * $c, 2);
    }
    
    /**
     * هل الصيدلية مفتوحة الآن؟
     */
    private function isOpenNow($schedules)
    {
        $today = now()->format('l'); // Monday, Tuesday...
        $currentTime = now()->format('H:i:s');
        
        $schedule = $schedules->where('day', $today)->first();
        
        if (!$schedule || $schedule->is_closed) {
            return false;
        }
        
        return $currentTime >= $schedule->open_time && $currentTime <= $schedule->close_time;
    }
}