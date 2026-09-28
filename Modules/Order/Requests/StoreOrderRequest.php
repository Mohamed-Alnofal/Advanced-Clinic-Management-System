<?php

namespace Modules\Order\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Pharmacy\Models\Pharmacy;

class StoreOrderRequest extends FormRequest
{
    /**
     * تحديد ما إذا كان المستخدم مصرح له بتنفيذ هذا الطلب
     */
    public function authorize(): bool
    {
        // فقط المستخدمين المسجلين يمكنهم إنشاء طلبات
        return auth()->check();
    }

    /**
     * قواعد التحقق من صحة البيانات
     */
    public function rules(): array
    {
        return [
            // معرف الصيدلية (مطلوب ويجب أن يكون موجوداً في قاعدة البيانات)
            'pharmacy_id' => 'required|exists:pharmacies,id',
            
            // عنوان التوصيل (مطلوب)
            // 'delivery_address' => 'required|string|max:500',
            
            // // إحداثيات الموقع (اختيارية)
            // 'latitude' => 'nullable|numeric',
            // 'longitude' => 'nullable|numeric',
            
            // حالة الدفع (اختيارية، افتراضي: pending)
            'payment_status' => 'nullable|in:pending,paid,cash_on_delivery',
            
            // الأدوية (مطلوب، على الأقل دواء واحد)
            'items' => 'required|array|min:1',
            'items.*.drug_id' => 'required|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.prescription_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }

    /**
     * رسائل الخطأ المخصصة
     */
    public function messages(): array
    {
        return [
            'pharmacy_id.required' => 'معرف الصيدلية مطلوب',
            'pharmacy_id.exists' => 'الصيدلية غير موجودة',
            // 'delivery_address.required' => 'عنوان التوصيل مطلوب',
            'items.required' => 'يجب إضافة دواء واحد على الأقل',
            'items.*.drug_id.required' => 'معرف الدواء مطلوب',
            'items.*.drug_id.exists' => 'الدواء غير موجود',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.prescription_file.mimes' => 'الوصفة الطبية يجب أن تكون صورة (jpg, png) أو PDF',
            'items.*.prescription_file.max' => 'حجم ملف الوصفة يجب أن لا يتجاوز 5 ميجابايت',
        ];
    }

    /**
     * تجهيز البيانات قبل التحقق
     */
    protected function prepareForValidation(): void
    {
        // دعم التوافق مع الإصدارات القديمة التي تستخدم pharmacist_id
        if ($this->has('pharmacist_id') && ! $this->has('pharmacy_id')) {
            $pharmacy = Pharmacy::where('pharmacist_id', $this->pharmacist_id)->first();

            if ($pharmacy) {
                $this->merge(['pharmacy_id' => $pharmacy->id]);
            }
        }
    }
}