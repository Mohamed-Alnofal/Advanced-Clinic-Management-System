<?php

namespace Modules\Drug\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Drug\Services\DrugService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Drug\Http\Resources\DrugResource;
use Illuminate\Http\JsonResponse;

class DrugController extends Controller
{
    public function __construct(
        protected DrugService $drugService
    ) {
        // $this->middleware(['auth:api', 'role:pharmacist']);
            $this->middleware(['auth:api', 'role:pharmacist'])->except(['autocomplete']);
    
    }

    /**
     * 📋 عرض جميع الأدوية (خاصة بالصيدلي فقط)
     * Caches the resolved JSON array — never the Paginator object.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $page = (int) $request->get('page', 1);
        $pharmacistId = auth()->id();

        $cacheKey = "drugs_pharmacist_{$pharmacistId}_page_{$page}_per_{$perPage}_payload";

        try {
            $payload = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($perPage) {
                $paginator = $this->drugService->getAll($perPage);

                return DrugResource::collection($paginator)->resolve();
            });
        } catch (\Throwable $e) {
            Log::warning('DrugController: cache fallback for index', ['error' => $e->getMessage()]);
            $paginator = $this->drugService->getAll($perPage);
            $payload = DrugResource::collection($paginator)->resolve();
        }

        $etag = '"'.md5(json_encode($payload)).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->header('ETag', $etag);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ])->header('ETag', $etag);
    }

    /**
     * 📋 عرض أدوية صيدلية محددة
     */
    public function getByPharmacy(Request $request, $pharmacyId)
    {
        $perPage = $request->get('per_page', 15);

        $drugs = $this->drugService->getByPharmacy($pharmacyId, $perPage);

        return response()->json([
            'success' => true,
            'data' => DrugResource::collection($drugs),
        ]);
    }

    /**
     * 💊 إضافة دواء جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'name' => 'required|string|max:255',
            'scientific_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'indication' => 'nullable|string',
            'state' => 'nullable|string',
            'pharmacodynamics' => 'nullable|string',
            'toxicity' => 'nullable|string',
            'half_life' => 'nullable|string',
            'route_of_elimination' => 'nullable|string',
            'clearance' => 'nullable|string',
            'attribute' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'synonyms' => 'nullable|array',
            'synonyms.*' => 'string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $drug = $this->drugService->create($data);

        $this->clearPharmacistDrugCache();

        return response()->json([
            'success' => true,
            'message' => '✅ تم إضافة الدواء بنجاح',
            'data' => new DrugResource($drug),
        ], 201);
    }

    /**
     * 🔍 عرض دواء محدد — no Eloquent model caching.
     */
    public function show($id)
    {
        $drug = $this->drugService->getById($id);

        return response()->json([
            'success' => true,
            'data' => new DrugResource($drug),
        ]);
    }

    /**
     * ✏️ تحديث دواء
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'scientific_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'indication' => 'nullable|string',
            'state' => 'nullable|string',
            'pharmacodynamics' => 'nullable|string',
            'toxicity' => 'nullable|string',
            'half_life' => 'nullable|string',
            'route_of_elimination' => 'nullable|string',
            'clearance' => 'nullable|string',
            'attribute' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'synonyms' => 'nullable|array',
            'category_ids' => 'nullable|array',
        ]);

        $drug = $this->drugService->update($id, $data);

        $this->clearPharmacistDrugCache();

        return response()->json([
            'success' => true,
            'message' => '✅ تم تحديث الدواء بنجاح',
            'data' => new DrugResource($drug),
        ]);
    }

    /**
     * 🔎 البحث عن دواء
     */
    public function search(Request $request)
    {
        $request->validate([
            'keyword' => 'required|string|min:2',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $keyword = $request->keyword;
        $perPage = $request->get('per_page', 15);

        $results = $this->drugService->search($keyword, $perPage);

        return response()->json([
            'success' => true,
            'data' => DrugResource::collection($results),
        ]);
    }

    /**
     * 🗑️ حذف دواء
     */
    public function destroy($id)
    {
        $this->drugService->delete($id);

        $this->clearPharmacistDrugCache();

        return response()->json([
            'success' => true,
            'message' => '✅ تم حذف الدواء بنجاح',
        ]);
    }

    private function clearPharmacistDrugCache(): void
    {
        $pharmacistId = auth()->id();
        for ($page = 1; $page <= 20; $page++) {
            foreach ([15, 25, 50] as $perPage) {
                Cache::forget("drugs_pharmacist_{$pharmacistId}_page_{$page}_per_{$perPage}_payload");
                Cache::forget("drugs_pharmacist_{$pharmacistId}_page_{$page}_per_{$perPage}");
            }
        }
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $results = $this->drugService->autocomplete(
            $validated['q'],
            $validated['limit'] ?? 10
        );

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
