<?php
// Modules/Pharmacy/Models/PharmacyDrug.php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Drug\Models\Drug;  // ✅ أضف هذا السطر

class PharmacyDrug extends Pivot
{
    protected $table = 'pharmacy_drug';
    
    protected $fillable = [
        'pharmacy_id',
        'drug_id',
        'price',
        'quantity',
        'is_available'
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'is_available' => 'boolean'
    ];
    
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
    
    public function drug()
    {
        return $this->belongsTo(Drug::class);  // ✅ صحيح
    }
}