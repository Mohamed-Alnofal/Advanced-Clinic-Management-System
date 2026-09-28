<?php

namespace Modules\Search\Http\Controllers;
use Modules\Search\Services\SearchService;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Search\Resources\DoctorSearchResource;

class SearchController extends Controller
{
    //
        public function searchByName(Request $request, SearchService $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        return DoctorSearchResource::collection(
            $service->searchByName($request->name)
        );
    }

        public function searchByGovernorate(Request $request, SearchService $service)
    {
        $doctors = $service->searchByGovernorateAndSpecialization($request->all());

        return DoctorSearchResource::collection($doctors);
    }

    public function searchByLocation(Request $request, SearchService $service)
    {
        $doctors = $service->searchByLocation($request->all());

        return DoctorSearchResource::collection($doctors);
    }


    /**
     * عرض جميع التخصصات
     */
    public function specializations(SearchService $service)
    {
        return response()->json([
            'status' => true,
            'data' => $service->getAllSpecializations()
        ]);
    }

    /**
     * عرض جميع المحافظات
     */
    public function governorates(SearchService $service)
    {
        return response()->json([
            'status' => true,
            'data' => $service->getAllGovernorates()
        ]);
    }
}
