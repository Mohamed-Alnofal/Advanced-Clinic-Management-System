<?php

namespace Modules\Drug\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Drug\Services\CategoryService;
class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * 📋 عرض جميع التصنيفات
     */
    public function index()
    {
        $categories = $this->categoryService->getAll();
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * 💊 إضافة تصنيف جديد (للصيدلي)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:255|unique:categories,category',
        ]);

        $category = $this->categoryService->create($data);

        return response()->json([
            'success' => true,
            'message' => '✅ تم إضافة التصنيف بنجاح',
            'data' => $category
        ], 201);
    }

    /**
     * ✏️ تحديث تصنيف
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'category' => 'required|string|max:255|unique:categories,category,' . $id,
        ]);

        $category = $this->categoryService->update($id, $data);

        return response()->json([
            'success' => true,
            'message' => '✅ تم تحديث التصنيف بنجاح',
            'data' => $category
        ]);
    }

    /**
     * 🗑️ حذف تصنيف
     */
    public function destroy($id)
    {
        $this->categoryService->delete($id);

        return response()->json([
            'success' => true,
            'message' => '✅ تم حذف التصنيف بنجاح'
        ]);
    }
}