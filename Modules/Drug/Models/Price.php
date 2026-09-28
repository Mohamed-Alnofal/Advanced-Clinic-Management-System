<?php

namespace Modules\Drug\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Price extends Model
{
    use HasFactory;

    protected $table = 'prices';

    protected $fillable = [
        'description',
        'cost',
        'currency',
        'unit',
        'drug_id',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * الدواء المرتبط بهذا السعر
     */
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    /**
     * نطاق السعر الحالي (آخر سعر)
     */
    public function scopeCurrent($query)
    {
        return $query->latest()->limit(1);
    }

    /**
     * نطاق بعملة محددة
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', strtoupper($currency));
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * تنسيق السعر مع العملة
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->cost, 2) . ' ' . $this->currency;
    }
}