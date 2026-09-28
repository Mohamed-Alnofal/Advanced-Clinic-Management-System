<?php

namespace Modules\Drug\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'labeller',
        'dosage_form',
        'strength',
        'route',
        'generic',
        'otc',
        'drug_id',
    ];

    protected $casts = [
        'generic' => 'boolean',
        'otc' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * الدواء العلمي المرتبط بهذا المنتج التجاري
     */
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * المنتجات المتاحة بدون وصفة طبية
     */
    public function scopeOverTheCounter($query)
    {
        return $query->where('otc', true);
    }

    /**
     * المنتجات التي تستلزم وصفة طبية
     */
    public function scopePrescriptionOnly($query)
    {
        return $query->where('otc', false);
    }

    /**
     * الأدوية الأصلية (غير الجنيسة)
     */
    public function scopeBrandName($query)
    {
        return $query->where('generic', false);
    }

    /**
     * الأدوية الجنيسة
     */
    public function scopeGeneric($query)
    {
        return $query->where('generic', true);
    }

    /**
     * البحث عن منتج بواسطة الشركة المصنعة
     */
    public function scopeByLabeller($query, $labeller)
    {
        return $query->where('labeller', 'LIKE', "%{$labeller}%");
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * هل المنتج أصلي أم جنيس (نص وصفي)
     */
    public function getTypeAttribute()
    {
        return $this->generic ? 'Generic' : 'Brand Name';
    }

    /**
     * نوع الوصفة (نص وصفي)
     */
    public function getPrescriptionTypeAttribute()
    {
        return $this->otc ? 'Over The Counter' : 'Prescription Required';
    }

    /**
     * الاسم الكامل للمنتج (الاسم التجاري + الشركة)
     */
    public function getFullNameAttribute()
    {
        return $this->name . ' - ' . $this->labeller;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * تنسيق معلومات الجرعة
     */
    public function formatDosage()
    {
        return "{$this->strength} - {$this->dosage_form} ({$this->route})";
    }
}