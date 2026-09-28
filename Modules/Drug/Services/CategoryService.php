<?php

namespace Modules\Drug\Services;

use Modules\Drug\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    public const ALL_CATEGORIES_CACHE_KEY = 'all_categories';

    /**
     * 📋 جلب جميع التصنيفات
     */
    public function getAll()
    {
        return Cache::remember(self::ALL_CATEGORIES_CACHE_KEY, now()->addDays(7), function () {
            return Category::all(['id', 'category']);
        });
    }

    /**
     * 💊 إنشاء تصنيف جديد
     */
    public function create(array $data)
    {
        $category = Category::create($data);
        $this->forgetCategoryCaches();

        return $category;
    }

    /**
     * ✏️ تحديث تصنيف
     */
    public function update($id, array $data)
    {
        $category = Category::findOrFail($id);
        $category->update($data);
        $this->forgetCategoryCaches();

        return $category;
    }

    /**
     * 🗑️ حذف تصنيف
     */
    public function delete($id)
    {
        $category = Category::findOrFail($id);
        $deleted = $category->delete();
        $this->forgetCategoryCaches();

        return $deleted;
    }

    public function forgetCategoryCaches(): void
    {
        Cache::forget(self::ALL_CATEGORIES_CACHE_KEY);
        if (auth()->id()) {
            Cache::forget('categories_all_pharmacist_'.auth()->id());
        }
    }
}