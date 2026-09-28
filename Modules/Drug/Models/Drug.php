<?php
// Modules/Drug/Models/Drug.php

namespace Modules\Drug\Models;

use Illuminate\Database\Eloquent\Model;

// استيراد الموديلات المرتبطة لكي تظهر وتعمل بوضوح
use Modules\Pharmacist\Models\Pharmacist;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\Drug\Models\Category;
use Modules\Drug\Models\Price;
use Modules\Drug\Models\Synonym;
use Modules\Drug\Models\Product;
use Modules\Drug\Models\Interaction;
use Modules\Drug\Models\Dosage;

class Drug extends Model
{
    protected $table = 'drugs';
    
    protected $fillable = [
        'name',
        'description',
        'state',
        'indication',
        'pharmacodynamics',
        'toxicity',
        'half_life',
        'route_of_elimination',
        'clearance',
        'attribute'
    ];

    // العلاقة مع الصيادلة
    public function pharmacists()
    {
        return $this->belongsToMany(Pharmacist::class, 'drug_pharmacist')
                    ->withTimestamps();
    }

    // العلاقة مع التصنيفات
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_drug')
                    ->withTimestamps();
    }

    // العلاقة مع الأسعار
    public function prices()
    {
        return $this->hasMany(Price::class, 'drug_id');
    }

    // العلاقة مع مرادفات الدواء
    public function synonyms()
    {
        return $this->hasMany(Synonym::class, 'drug_id');
    }

    // العلاقة مع الصيدليات
    public function pharmacies()
    {
        return $this->belongsToMany(Pharmacy::class, 'pharmacy_drug')
                    ->withPivot(['price', 'quantity', 'is_available'])
                    ->withTimestamps();
    }

    // العلاقة مع المنتجات التجارية
    public function products()
    {
        return $this->hasMany(Product::class, 'drug_id');
    }

    // العلاقة مع التفاعلات الدوائية
    public function interactions()
    {
        return $this->hasMany(Interaction::class, 'drug_id');
    }

    // العلاقة مع الجرعات
    public function dosages()
    {
        return $this->hasMany(Dosage::class, 'drug_id');
    }

    // أحدث سعر (Accessor)
    public function getLatestPriceAttribute()
    {
        return $this->prices()->latest()->first();
    }

    // العلاقة مع الأدوية البديلة
    public function alternatives()
    {
        return $this->belongsToMany(
            Drug::class,   
            'drug_alternatives',
            'drug_id',
            'alternative_id'
        )->withTimestamps();
    }
}