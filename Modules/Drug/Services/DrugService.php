<?php

namespace Modules\Drug\Services;  

use Modules\Drug\Models\Drug;
use Modules\Drug\Models\Synonym;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\Pharmacy\Models\PharmacyDrug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class DrugService
{
    /**
     * 💊 إنشاء دواء جديد (مع ربطه بالصيدلية عبر pharmacy_drug)
     */
 public function create(array $data)
{
    return DB::transaction(function () use ($data) {

        $user = Auth::user();
        $pharmacistId = $user->pharmacist->id ?? null;

        // التحقق من ملكية الصيدلية
        Pharmacy::where('id', $data['pharmacy_id'])
            ->where('pharmacist_id', $pharmacistId)
            ->firstOrFail();

        // ==========================================
        // البحث عن الدواء أولاً
        // ==========================================
        $drug = Drug::whereRaw(
            'LOWER(name) = ?',
            [strtolower(trim($data['name']))]
        )->first();

        // إذا لم يكن موجوداً ننشئه
        if (!$drug) {

            $drugData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'indication' => $data['indication'] ?? null,
                'state' => $data['state'] ?? null,
                'pharmacodynamics' => $data['pharmacodynamics'] ?? null,
                'toxicity' => $data['toxicity'] ?? null,
                'half_life' => $data['half_life'] ?? null,
                'route_of_elimination' => $data['route_of_elimination'] ?? null,
                'clearance' => $data['clearance'] ?? null,
                'attribute' => $data['attribute'] ?? null,
            ];

            $drug = Drug::create($drugData);

            // إضافة المرادفات لأول مرة فقط
            if (!empty($data['synonyms'])) {

                foreach ($data['synonyms'] as $synonymName) {

                    Synonym::create([
                        'synonym' => $synonymName,
                        'drug_id' => $drug->id,
                    ]);
                }
            }

            // إضافة التصنيفات لأول مرة فقط (بدون تكرار)
            if (!empty($data['category_ids'])) {
                $drug->categories()->syncWithoutDetaching(
                    array_values(array_unique(array_map('intval', $data['category_ids'])))
                );
            }

            // إضافة السعر المرجعي لأول مرة فقط
            if (isset($data['price'])) {

                $drug->prices()->create([
                    'description' => 'السعر الأساسي',
                    'cost' => $data['price'],
                    'currency' => $data['currency'] ?? 'USD',
                    'unit' => $data['unit'] ?? 'وحدة',
                ]);
            }
        }

        // ==========================================
        // التحقق هل الصيدلية تملك الدواء مسبقاً
        // ==========================================
        $pharmacyDrug = PharmacyDrug::where(
                'pharmacy_id',
                $data['pharmacy_id']
            )
            ->where(
                'drug_id',
                $drug->id
            )
            ->first();

        // إذا موجود نزيد الكمية فقط
        if ($pharmacyDrug) {

            $pharmacyDrug->update([
                'quantity' => $pharmacyDrug->quantity + ($data['quantity'] ?? 0),
                'price' => $data['price'] ?? $pharmacyDrug->price,
                'is_available' => true,
            ]);
        } else {

            // إذا غير موجود ننشئ علاقة جديدة
            PharmacyDrug::create([
                'pharmacy_id' => $data['pharmacy_id'],
                'drug_id' => $drug->id,
                'price' => $data['price'],
                'quantity' => $data['quantity'] ?? 0,
                'is_available' => $data['is_available'] ?? true,
            ]);
        }

        $pharmacyDrug = PharmacyDrug::where('pharmacy_id', $data['pharmacy_id'])
            ->where('drug_id', $drug->id)
            ->first();

        $drug->load([
            'synonyms',
            'categories',
            'prices',
            'pharmacies',
        ]);

        if ($pharmacyDrug) {
            $drug->setAttribute('price', $pharmacyDrug->price);
            $drug->setAttribute('quantity', $pharmacyDrug->quantity);
            $drug->setAttribute('is_available', $pharmacyDrug->is_available);
        }

        return $drug;
    });
}

    /**
     * 📋 جلب كل الأدوية (خاصة بالصيدلي فقط عبر جدول pharmacy_drug)
     */
    public function getAll($perPage = 15)
{
    $user = Auth::user();
    $pharmacistId = $user->pharmacist->id ?? null;
    
    // جلب جميع الصيدليات التابعة للصيدلي
    $pharmacyIds = Pharmacy::where('pharmacist_id', $pharmacistId)->pluck('id');
    
    // جلب الأدوية عبر جدول pharmacy_drug مع تحميل العلاقات
    $pharmacyDrugs = PharmacyDrug::whereIn('pharmacy_id', $pharmacyIds)
        ->with(['drug' => function($query) {
            $query->with(['synonyms', 'categories']);  // ✅ تحميل المرادفات والتصنيفات
        }])
        ->latest()
        ->paginate($perPage);
    
    // تحويل البيانات مع الاحتفاظ بالعلاقات
    $pharmacyDrugs->getCollection()->transform(function ($item) {
        return $this->presentPharmacyDrug($item);
    });
    
    return $pharmacyDrugs;
}

    /**
     * 📋 جلب أدوية صيدلية محددة
     */
   public function getByPharmacy($pharmacyId, $perPage = 15)
{
    $user = Auth::user();
    $pharmacistId = $user->pharmacist->id ?? null;
    
    // ✅ التحقق أن الصيدلية تتبع هذا الصيدلي
    Pharmacy::where('id', $pharmacyId)
        ->where('pharmacist_id', $pharmacistId)
        ->firstOrFail();
    
    $pharmacyDrugs = PharmacyDrug::where('pharmacy_id', $pharmacyId)
        ->with(['drug' => function($query) {
            $query->with(['synonyms', 'categories']);  // ✅ تحميل المرادفات والتصنيفات
        }])
        ->latest()
        ->paginate($perPage);
    
    // تحويل البيانات
    $pharmacyDrugs->getCollection()->transform(function ($item) {
        return $this->presentPharmacyDrug($item);
    });
    
    return $pharmacyDrugs;
}

    /**
     * 👁️ جلب دواء محدد (مع التحقق من ملكية الصيدلي)
     */
    public function getById($id)
{
    $user = Auth::user();
    $pharmacistId = $user->pharmacist->id ?? null;
    
    $pharmacyIds = Pharmacy::where('pharmacist_id', $pharmacistId)->pluck('id');
    
    $pharmacyDrug = PharmacyDrug::where('drug_id', $id)
        ->whereIn('pharmacy_id', $pharmacyIds)
        ->with(['drug' => function($query) {
            $query->with(['synonyms', 'categories']);  // ✅ تحميل المرادفات والتصنيفات
        }])
        ->firstOrFail();
    
    return $this->presentPharmacyDrug($pharmacyDrug);
}

    /**
     * ✏️ تحديث دواء (تحديث السعر والكمية في جدول pharmacy_drug)
     */
    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $user = Auth::user();
            $pharmacistId = $user->pharmacist->id ?? null;
            
            $pharmacyIds = Pharmacy::where('pharmacist_id', $pharmacistId)->pluck('id');
            
            $pharmacyDrug = PharmacyDrug::where('drug_id', $id)
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->firstOrFail();
            
            // تحديث السعر والكمية
            if (isset($data['price']) || isset($data['quantity']) || isset($data['is_available'])) {
                $pharmacyDrug->update([
                    'price' => $data['price'] ?? $pharmacyDrug->price,
                    'quantity' => $data['quantity'] ?? $pharmacyDrug->quantity,
                    'is_available' => $data['is_available'] ?? $pharmacyDrug->is_available,
                ]);
            }
            
            // تحديث معلومات الدواء الأساسية
            $drug = Drug::findOrFail($id);
            $drugData = [];
            
            $allowedFields = ['name', 'description', 'indication', 'state', 
                              'pharmacodynamics', 'toxicity', 'half_life', 
                              'route_of_elimination', 'clearance', 'attribute'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $drugData[$field] = $data[$field];
                }
            }
            
            if (!empty($drugData)) {
                $drug->update($drugData);
            }

            if (array_key_exists('category_ids', $data) && is_array($data['category_ids'])) {
                $drug->categories()->sync(
                    array_values(array_unique(array_map('intval', $data['category_ids'])))
                );
            }
            
            $pharmacyDrug->refresh();
            $drug->load(['synonyms', 'categories', 'prices']);

            return $this->presentPharmacyDrug($pharmacyDrug->setRelation('drug', $drug));
        });
    }

    /**
     * 🔎 البحث عن دواء (خاص بالصيدلي فقط)
     */
   public function search($keyword, $perPage = 15)
{
    $user = Auth::user();
    $pharmacistId = $user->pharmacist->id ?? null;
    
    $pharmacyIds = Pharmacy::where('pharmacist_id', $pharmacistId)->pluck('id');
    
    $pharmacyDrugs = PharmacyDrug::whereIn('pharmacy_id', $pharmacyIds)
        ->whereHas('drug', function ($query) use ($keyword) {
            $query->where('name', 'LIKE', "%{$keyword}%")
                ->orWhere('description', 'LIKE', "%{$keyword}%")
                ->orWhereHas('synonyms', function ($q) use ($keyword) {
                    $q->where('synonym', 'LIKE', "%{$keyword}%");
                });
        })
        ->with(['drug' => function($query) {
            $query->with(['synonyms', 'categories']);  // ✅ تحميل المرادفات والتصنيفات
        }])
        ->paginate($perPage);
    
    // تحويل البيانات
    $pharmacyDrugs->getCollection()->transform(function ($item) {
        return $this->presentPharmacyDrug($item);
    });
    
    return $pharmacyDrugs;
}

    /**
     * Attach pharmacy stock fields onto the drug model for API responses.
     */
    private function presentPharmacyDrug(PharmacyDrug $pharmacyDrug): Drug
    {
        $drug = $pharmacyDrug->drug;
        if (! $drug) {
            throw new \RuntimeException('Pharmacy drug is missing its drug relation.');
        }

        if (! $drug->relationLoaded('synonyms') || ! $drug->relationLoaded('categories')) {
            $drug->loadMissing(['synonyms', 'categories']);
        }

        // Use setAttribute so JsonResource can read pharmacy stock even when
        // the drugs table also has a legacy quantity column.
        $drug->setAttribute('price', $pharmacyDrug->price);
        $drug->setAttribute('quantity', $pharmacyDrug->quantity);
        $drug->setAttribute('is_available', $pharmacyDrug->is_available);

        return $drug;
    }

    /**
     * 🗑️ حذف دواء (حذف العلاقة من pharmacy_drug)
     */
    public function delete($id)
    {
        $user = Auth::user();
        $pharmacistId = $user->pharmacist->id ?? null;
        
        $pharmacyIds = Pharmacy::where('pharmacist_id', $pharmacistId)->pluck('id');
        
        $pharmacyDrug = PharmacyDrug::where('drug_id', $id)
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->firstOrFail();
        
        return $pharmacyDrug->delete();
    }

    public function autocomplete(string $query, int $limit = 10)
{
    $query = trim($query);

    if ($query === '') {
        return collect();
    }

    // البحث في الاسم الأساسي للدواء
    $drugs = Drug::query()
        ->where('name', 'LIKE', $query . '%')
        ->get([
            'id',
            'name',
        ])
        ->map(function ($drug) {
            return [
                'drug_id'        => $drug->id,
                'name'           => $drug->name,
                'matched_by'     => 'drug_name',
                'matched_value'  => $drug->name,
            ];
        });

    // البحث في الأسماء العلمية / المرادفات
    $synonyms = Synonym::query()
        ->with('drug:id,name')
        ->where('synonym', 'LIKE', $query . '%')
        ->get([
            'id',
            'synonym',
            'drug_id',
        ])
        ->filter(fn ($synonym) => $synonym->drug !== null)
        ->map(function ($synonym) {
            return [
                'drug_id'        => $synonym->drug->id,
                'name'           => $synonym->drug->name,
                'matched_by'     => 'synonym',
                'matched_value'  => $synonym->synonym,
            ];
        });

    return $drugs
        ->merge($synonyms)
        ->unique('drug_id')
        ->take($limit)
        ->values();
}
}