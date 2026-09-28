<?php

namespace App\Repositories;

use Modules\Drug\Models\Drug;
use Illuminate\Support\Facades\Cache;

class DrugRepository
{
    /**
     * البحث عن دواء بالاسم أو الاسم العلمي
     */
    public function searchByName($keyword)
    {
        $cacheKey = "drug_search_{$keyword}";
        
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($keyword) {
            return Drug::where('name', 'LIKE', "%{$keyword}%")
                ->orWhere('scientific_name', 'LIKE', "%{$keyword}%")
                ->where('quantity', '>', 0)  // المتوفر فقط
                ->with('alternatives')
                ->get();
        });
    }
    
    /**
     * جلب دواء محدد مع بدائله
     */
    public function findWithAlternatives($id)
    {
        $cacheKey = "drug_with_alternatives_{$id}";
        
        return Cache::remember($cacheKey, now()->addDay(), function () use ($id) {
            return Drug::with('alternatives')->findOrFail($id);
        });
    }
}