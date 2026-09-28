<?php

namespace Modules\MedicalRecords\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalReferenceRange extends Model
{
    protected $table = 'medical_reference_ranges';

    protected $fillable = [
        'test_name',
        'test_key',
        'unit',
        'min_value',
        'max_value',
        'gender',
        'age_min',
        'age_max',
        'description',
        'advice_low',
        'advice_high',
        'interpretation_low',
        'interpretation_high',
        'interpretation_normal',
    ];

    protected $casts = [
        'min_value' => 'float',
        'max_value' => 'float',
        'age_min' => 'integer',
        'age_max' => 'integer',
    ];

    /**
     * تقييم القيمة ومقارنتها مع النطاق الطبيعي
     */
    public function evaluate(float $value, string $gender = 'all', ?int $age = null): array
    {
        // التحقق من الجنس (تخطي إذا طُلب تقييم عام)
        if ($gender !== 'all' && $this->gender !== 'all' && $this->gender !== $gender) {
            return [
                'status' => 'unknown',
                'message' => 'هذا الفحص خاص بالجنس الآخر',
                'advice' => 'يرجى التحقق من الجنس المناسب',
            ];
        }

        // التحقق من العمر
        if ($age !== null) {
            if ($this->age_min !== null && $age < $this->age_min) {
                return [
                    'status' => 'unknown',
                    'message' => 'العمر أقل من النطاق المطلوب',
                    'advice' => 'يرجى التحقق من العمر المناسب',
                ];
            }
            if ($this->age_max !== null && $age > $this->age_max) {
                return [
                    'status' => 'unknown',
                    'message' => 'العمر أكبر من النطاق المطلوب',
                    'advice' => 'يرجى التحقق من العمر المناسب',
                ];
            }
        }

        if ($value < $this->min_value) {
            return [
                'status' => 'low',
                'message' => $this->interpretation_low ?? "القيمة منخفضة (أقل من {$this->min_value} {$this->unit})",
                'advice' => $this->advice_low ?? 'يُنصح باستشارة الطبيب',
            ];
        }

        if ($value > $this->max_value) {
            return [
                'status' => 'high',
                'message' => $this->interpretation_high ?? "القيمة مرتفعة (أعلى من {$this->max_value} {$this->unit})",
                'advice' => $this->advice_high ?? 'يُنصح باستشارة الطبيب',
            ];
        }

        return [
            'status' => 'normal',
            'message' => $this->interpretation_normal ?? "القيمة ضمن النطاق الطبيعي ({$this->min_value} - {$this->max_value} {$this->unit})",
            'advice' => 'القيمة طبيعية، استمر في الحفاظ على صحتك',
        ];
    }

    /**
     * الحصول على النطاق الطبيعي كنص
     */
    public function getRangeAttribute(): string
    {
        if ($this->min_value !== null && $this->max_value !== null) {
            return "{$this->min_value} - {$this->max_value} {$this->unit}";
        }
        return 'غير محدد';
    }
}