<?php
namespace Modules\Search\Services;

use Modules\Core\Models\Governorate;
use Modules\Core\Models\specializations;
use Modules\Doctors\Models\Doctor;

// use Modules\Core\Models\Governorate;
// use Modules\Core\Models\specializations;
// use Modules\Doctors\Models\Doctor;

class SearchService
{


    public function searchByName(string $name)
    {
        return Doctor::with(['user', 'specialization', 'clinics.medicalCenter'])
            ->listedForPatients()
            ->whereHas('user', function ($q) use ($name) {
                $q->where('name', 'like', "%$name%");
            })
            ->paginate(10);
    }

    // public function searchByGovernorateAndSpecialization(array $data)
    // {
    //     return Doctor::with(['user', 'specialization'])
    //         ->when(!empty($data['governorate_id']), function ($q) use ($data) {
    //             $q->where('governorate_id', $data['governorate_id']);
    //         })
    //         ->when(!empty($data['specialization_id']), function ($q) use ($data) {
    //             $q->where('specialization_id', $data['specialization_id']);
    //         })
    //         ->get();
    // }

    public function searchByGovernorateAndSpecialization(array $data)
    {
        return Doctor::with([
                'user',
                'specialization',
                'clinics.medicalCenter'
            ])
            ->listedForPatients()

            ->when(!empty($data['specialization_id']), function ($q) use ($data) {

                $q->where(
                    'specialization_id',
                    $data['specialization_id']
                );

            })

            ->when(!empty($data['governorate_id']), function ($q) use ($data) {

                $q->whereHas('clinics.medicalCenter', function ($query) use ($data) {

                    $query->where(
                        'governorate_id',
                        $data['governorate_id']
                    );

                });

            })

            ->distinct()
            ->get();
    }
    // public function searchByLocation(array $data)
    // {   
    //     $lat = $data['latitude'];
    //     $lng = $data['longitude'];
    //     $radius = $data['radius'] ?? 10; // كم km

    //     return Doctor::with(['user', 'specialization'])
    //         ->selectRaw("
    //             *, 
    //             (6371 * acos(
    //                 cos(radians(?)) 
    //                 * cos(radians(clinic_latitude)) 
    //                 * cos(radians(clinic_longitude) - radians(?)) 
    //                 + sin(radians(?)) 
    //                 * sin(radians(clinic_latitude))
    //             )) AS distance
    //         ", [$lat, $lng, $lat])

    //         ->when(!empty($data['specialization_id']), function ($q) use ($data) {
    //             $q->where('specialization_id', $data['specialization_id']);
    //         })

    //         ->having("distance", "<=", $radius)
    //         ->orderBy("distance")

    //         ->paginate(10);
    // }
    public function searchByLocation(array $data)
    {
        $lat = $data['latitude'];
        $lng = $data['longitude'];
        $radius = $data['radius'] ?? 1;

        return Doctor::with(['user', 'specialization'])
            ->listedForPatients()
            ->join('clinics', 'clinics.doctor_id', '=', 'doctors.id')
            ->join('medical_facilities', 'medical_facilities.id', '=', 'clinics.medical_facility_id')
            ->where('medical_facilities.is_verified', true)
            ->where(function ($q) {
                $q->where('medical_facilities.status', 'approved')
                    ->orWhereNull('medical_facilities.status');
            })

            ->select(
                'doctors.*',
                'medical_facilities.latitude',
                'medical_facilities.longitude'
            )
            // ->select('doctors.*')

            ->selectRaw("
                (6371 * acos(
                    cos(radians(?))
                    * cos(radians(medical_facilities.latitude))
                    * cos(radians(medical_facilities.longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(medical_facilities.latitude))
                )) AS distance
            ", [$lat, $lng, $lat])

            ->when(!empty($data['specialization_id']), function ($q) use ($data) {
                $q->where('doctors.specialization_id', $data['specialization_id']);
            })

            ->having('distance', '<=', $radius)
            ->orderBy('distance')

            ->distinct()
            ->paginate(10);
    }


    public function getAllSpecializations()
    {
        return specializations::orderBy('name')
            ->get()
            ->unique(fn ($row) => mb_strtolower(trim((string) $row->name)))
            ->values();
    }
    public function getAllGovernorates()
    {
        return Governorate::orderBy('name')
            ->get()
            ->unique(fn ($row) => mb_strtolower(trim((string) $row->name)))
            ->values();
    }
}