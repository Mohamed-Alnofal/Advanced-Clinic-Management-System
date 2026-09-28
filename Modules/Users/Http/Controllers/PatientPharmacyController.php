<?php

    namespace Modules\Users\Http\Controllers;

    use Illuminate\Http\Request;
    use Modules\Core\Http\Controllers\Controller;
    use Modules\Users\Services\PatientPharmacyService;
    use Illuminate\Support\Facades\Cache;

    class PatientPharmacyController extends Controller
    {
        public function __construct(
            protected PatientPharmacyService $service
        ) {}

        /**
         * 🏥 الصيدليات القريبة
         * GET /api/patient/pharmacies/nearby?latitude=30.0444&longitude=31.2357
         */
        public function nearby(Request $request)
        {
            $data = $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            $result = $this->service->nearby($data['latitude'], $data['longitude']);

            return response()->json($result);
        }

        /**
         * 💊 البحث عن دواء
         * GET /api/patient/drugs/search?keyword=panadol&latitude=30.0444&longitude=31.2357
         */
        public function searchDrug(Request $request)
        {
            $data = $request->validate([
                'keyword' => 'required|string|min:2',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            $result = $this->service->searchDrug(
                $data['keyword'],
                $data['latitude'],
                $data['longitude']
            );

            return response()->json($result);
        }

        /**
         * 💊 اقتراح أسماء الأدوية
         * GET /api/patient/drugs/suggestions?keyword=pan
         */
        public function suggestDrugs(Request $request)
        {
            $data = $request->validate([
                'keyword' => 'required|string|min:2',
            ]);

            $result = $this->service->suggestDrugs($data['keyword']);

            return response()->json($result);
        }

        /**
         * 📦 تفاصيل دواء محدد
         * GET /api/patient/drugs/{id}/availability?latitude=30.0444&longitude=31.2357
         */
        public function drugAvailability(Request $request, $id)
        {
            $data = $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            $result = $this->service->drugDetails($id, $data['latitude'], $data['longitude']);

            return response()->json($result);
        }



                public function pharmaciesByGovernorate(Request $request)
        {
            $data = $request->validate([
                'name' => 'required|string|min:2'
            ]);

            $result = $this->service->pharmaciesByGovernorateName(
                $data['name']
            );

            return response()->json($result);
        }
    }