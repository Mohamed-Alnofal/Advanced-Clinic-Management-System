<?php

namespace Modules\Ambulance\Services;

use Modules\Ambulance\Models\AmbulanceUnit;

class NearestAmbulanceService
{
    private const EARTH_RADIUS_KM = 6371;

    /**
     * Find the nearest available ambulance unit with a driver and GPS fix.
     * Distance is computed in SQL (Haversine) — only one row is hydrated.
     */
    public function findNearest(
        float $latitude,
        float $longitude,
        ?float $maxKm = 50.0,
        array $excludeUnitIds = [],
    ): ?AmbulanceUnit {
        $haversine = '('.self::EARTH_RADIUS_KM.' * acos('
            .'LEAST(1, GREATEST(-1, '
            .'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))'
            .' + sin(radians(?)) * sin(radians(latitude))'
            .'))'
            .'))';

        $query = AmbulanceUnit::query()
            ->with('driver')
            ->select('ambulance_units.*')
            ->selectRaw("{$haversine} as distance_km", [$latitude, $longitude, $latitude])
            ->where('status', 'available')
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('ambulance_driver_id')
            ->when($excludeUnitIds !== [], fn ($q) => $q->whereNotIn('id', $excludeUnitIds));

        if ($maxKm !== null) {
            $query->havingRaw('distance_km <= ?', [$maxKm]);
        }

        $best = $query
            ->orderBy('distance_km')
            ->limit(1)
            ->first();

        if ($best && $best->distance_km !== null) {
            $best->setAttribute('distance_km', round((float) $best->distance_km, 2));
        }

        return $best;
    }

    public function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Rough ETA in minutes assuming average urban speed (~35 km/h).
     */
    public function etaMinutes(float $distanceKm, float $avgSpeedKmh = 35.0): int
    {
        if ($distanceKm <= 0) {
            return 1;
        }

        return max(1, (int) ceil(($distanceKm / max($avgSpeedKmh, 1)) * 60));
    }
}
