<?php

namespace Modules\Users\Services;

use Modules\Pharmacy\Models\Pharmacy;
use Modules\Pharmacy\Models\PharmacyDrug;
use Modules\Drug\Models\Drug;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class PatientPharmacyService
{
    /**
     * 🏥 أقرب الصيدليات (FIXED VERSION)
     */
    public function nearby($lat, $lng, $governorateId = null, $perPage = 10)
    {
        $cacheKey = "nearby_pharmacies_{$lat}_{$lng}_{$governorateId}";

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use (
            $lat,
            $lng,
            $governorateId,
            $perPage
        ) {

            $radius = 50;

            // 1️⃣ جلب كل الصيدليات مع العلاقات (بدون JOIN نهائيًا)
            $pharmacies = Pharmacy::with([
                    'medicalFacility.governorate',
                    'schedules',
                    'pharmacist',
                ])
                ->whereHas('medicalFacility', function ($q) {
                    $q->where('type', 'pharmacy')
                        ->where('is_verified', true)
                        ->where(function ($status) {
                            $status->where('status', 'approved')
                                ->orWhereNull('status');
                        })
                        ->where(function ($active) {
                            $active->where('is_active', true)
                                ->orWhereNull('is_active');
                        });
                })
                ->whereHas('pharmacist', function ($q) {
                    $q->listedForPatients();
                })
                ->get();

            // 2️⃣ حساب المسافة + فلترة
            $mapped = $pharmacies
                ->map(function ($pharmacy) use ($lat, $lng, $governorateId) {

                    $facility = $pharmacy->medicalFacility;

                    if (!$facility || !$facility->latitude || !$facility->longitude) {
                        return null;
                    }

                    // فلترة المحافظة (إن وجدت)
                    if ($governorateId && $facility->governorate_id != $governorateId) {
                        return null;
                    }

                    $distance = $this->calculateDistance(
                        $lat,
                        $lng,
                        $facility->latitude,
                        $facility->longitude
                    );

                    return [
                        'pharmacy' => $pharmacy,
                        'facility' => $facility,
                        'distance' => $distance,
                    ];
                })
                ->filter()
                ->filter(fn($item) => $item['distance'] <= $radius)
                ->sortBy('distance')
                ->values();

            // 3️⃣ pagination صحيح
            $page = request()->get('page', 1);

            $paginated = new LengthAwarePaginator(
                $mapped->forPage($page, $perPage)->values(),
                $mapped->count(),
                $perPage,
                $page
            );

            // 4️⃣ response
            $paginated->getCollection()->transform(function ($item) {

                $pharmacy = $item['pharmacy'];
                $facility = $item['facility'];

                return [
                    'id' => $pharmacy->id,
                    'name' => $facility->name,

                    'governorate' => $facility->governorate?->name,

                    'address' => $facility->address,
                    'phone' => $facility->phone,

                    'location' => [
                        'latitude' => $facility->latitude,
                        'longitude' => $facility->longitude,
                    ],

                    'distance' => round($item['distance'], 2),
                    'distance_text' => round($item['distance'], 1) . ' كم',

                    'schedules' => $pharmacy->schedules,
                ];
            });

            return [
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ]
            ];
        });
    }

    /**
     * Patient medicine search (location-based).
     * Only returns drugs that have stock in nearby pharmacies.
     */
    public function searchDrug($keyword, $lat, $lng, $governorateId = null)
    {
        $keyword = trim((string) $keyword);
        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = 50;

        $drugs = Drug::query()
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhereHas('synonyms', function ($syn) use ($keyword) {
                        $syn->where('synonym', 'LIKE', "%{$keyword}%");
                    });
            })
            ->with(['synonyms', 'categories'])
            ->orderBy('name')
            ->limit(40)
            ->get();

        if ($drugs->isEmpty()) {
            return [
                'success' => true,
                'keyword' => $keyword,
                'count' => 0,
                'results' => [],
                'message' => 'No medicines match this name.',
            ];
        }

        $results = [];

        foreach ($drugs as $drug) {
            $nearby = $this->nearbyStockForDrug(
                (int) $drug->id,
                $lat,
                $lng,
                $governorateId,
                $radius
            );

            // Location workflow: hide catalog matches without nearby stock.
            if (count($nearby) === 0) {
                continue;
            }

            $results[] = [
                'drug_id' => $drug->id,
                'id' => $drug->id,
                'drug_name' => $drug->name,
                'name' => $drug->name,
                'description' => $drug->description,
                'indication' => $drug->indication,
                'state' => $drug->state,
                'synonyms' => $drug->synonyms->pluck('synonym')->values(),
                'categories' => $drug->categories
                    ->pluck('name')
                    ->filter()
                    ->values(),
                'has_nearby_stock' => true,
                'total_pharmacies' => count($nearby),
                'pharmacies' => collect($nearby)->map(function ($item) {
                    return [
                        'pharmacy_id' => $item['pharmacy_id'],
                        'name' => $item['name'],
                        'distance_km' => $item['distance'],
                        'distance_text' => $item['distance_text'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'phone' => $item['phone'],
                        'address' => $item['address'],
                    ];
                })->values(),
            ];
        }

        usort($results, function ($a, $b) {
            if ($a['total_pharmacies'] === $b['total_pharmacies']) {
                return strcmp((string) $a['drug_name'], (string) $b['drug_name']);
            }

            return $b['total_pharmacies'] <=> $a['total_pharmacies'];
        });

        return [
            'success' => true,
            'keyword' => $keyword,
            'count' => count($results),
            'results' => array_values($results),
            'message' => count($results) === 0
                ? 'No nearby pharmacies have this medicine in stock.'
                : null,
        ];
    }

        /**
     * Suggest medicine names for autocomplete.
     */
    public function suggestDrugs($keyword)
    {
        $keyword = trim((string) $keyword);

        $drugs = Drug::query()
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "{$keyword}%")
                    ->orWhereHas('synonyms', function ($syn) use ($keyword) {
                        $syn->where('synonym', 'LIKE', "{$keyword}%");
                    });
            })
            ->select(['id', 'name'])
            ->orderByRaw(
                "CASE
                    WHEN name LIKE ? THEN 0
                    ELSE 1
                END",
                ["{$keyword}%"]
            )
            ->orderBy('name')
            ->limit(10)
            ->get();

        return [
            'success' => true,
            'keyword' => $keyword,
            'count' => $drugs->count(),
            'suggestions' => $drugs->map(function ($drug) {
                return [
                    'id' => $drug->id,
                    'name' => $drug->name,
                ];
            })->values(),
        ];
    }
    /**
     * Stock availability for one drug near the patient.
     */
    public function drugDetails($drugId, $lat, $lng, $governorateId = null)
    {
        $drug = Drug::with(['synonyms', 'categories'])->find($drugId);

        if (! $drug) {
            return [
                'success' => false,
                'message' => 'Drug not found',
            ];
        }

        $availableIn = $this->nearbyStockForDrug(
            (int) $drug->id,
            (float) $lat,
            (float) $lng,
            $governorateId,
            50
        );

        return [
            'success' => true,
            'data' => [
                'drug' => [
                    'id' => $drug->id,
                    'name' => $drug->name,
                    'description' => $drug->description,
                    'indication' => $drug->indication,
                    'state' => $drug->state,
                    'pharmacodynamics' => $drug->pharmacodynamics,
                    'toxicity' => $drug->toxicity,
                    'half_life' => $drug->half_life,
                    'route_of_elimination' => $drug->route_of_elimination,
                    'clearance' => $drug->clearance,
                    'attribute' => $drug->attribute,
                    'synonyms' => $drug->synonyms,
                    'categories' => $drug->categories,
                ],
                'available_in' => $availableIn,
                'total_pharmacies' => count($availableIn),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nearbyStockForDrug(
        int $drugId,
        float $lat,
        float $lng,
        $governorateId,
        float $radiusKm
    ): array {
        $pharmacyDrugs = PharmacyDrug::query()
            ->where('drug_id', $drugId)
            ->where('quantity', '>', 0)
            ->where(function ($q) {
                $q->where('is_available', true)
                    ->orWhere('is_available', 1)
                    ->orWhereNull('is_available');
            })
            ->whereHas('pharmacy.pharmacist', function ($q) {
                $q->listedForPatients();
            })
            ->with([
                'pharmacy.medicalFacility.governorate',
                'pharmacy.schedules',
                'pharmacy.pharmacist',
            ])
            ->get();

        $nearby = [];

        foreach ($pharmacyDrugs as $pd) {
            $pharmacy = $pd->pharmacy;
            if (! $pharmacy) {
                continue;
            }

            $facility = $pharmacy->medicalFacility;
            if (! $facility || $facility->latitude === null || $facility->longitude === null) {
                continue;
            }

            // Skip inactive facilities only; allow newly created locations with stock.
            if ($facility->is_active === false) {
                continue;
            }

            if ($governorateId && (int) $facility->governorate_id !== (int) $governorateId) {
                continue;
            }

            $distance = $this->calculateDistance(
                $lat,
                $lng,
                (float) $facility->latitude,
                (float) $facility->longitude
            );

            if ($distance > $radiusKm) {
                continue;
            }

            $nearby[] = [
                'pharmacy_id' => $pharmacy->id,
                'name' => $facility->name,
                'governorate' => $facility->governorate?->name,
                'address' => $facility->address,
                'phone' => $facility->phone,
                'location' => [
                    'latitude' => $facility->latitude,
                    'longitude' => $facility->longitude,
                ],
                'distance' => round($distance, 2),
                'distance_text' => round($distance, 1).' كم',
                'price' => $pd->price,
                'quantity' => $pd->quantity,
                'is_available' => true,
                'schedules' => $pharmacy->schedules,
            ];
        }

        usort($nearby, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return $nearby;
    }

    /**
     * 📍 حساب المسافة
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2 +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($lonDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    //البحث من خلال المحافظة

 public function pharmaciesByGovernorateName($name)
{
    // ✅ البحث مباشرة في medical_facilities.governor_id
    $pharmacies = Pharmacy::with([
        'medicalFacility',
        'schedules'
    ])
    ->whereHas('medicalFacility', function ($q) use ($name) {
        $q->whereHas('governorate', function ($subQ) use ($name) {
            $subQ->where('name', 'LIKE', "%{$name}%");
        });
    })
    ->get();

    return [
        'success' => true,
        'count' => $pharmacies->count(),
        'data' => $pharmacies->map(function ($pharmacy) {
            $facility = $pharmacy->medicalFacility;
            return [
                'pharmacy_id' => $pharmacy->id,
                'name' => $facility?->name,
                'governorate' => $facility?->governorate?->name,
                'address' => $facility?->address,
                'phone' => $facility?->phone,
            ];
        })
    ];
}
}