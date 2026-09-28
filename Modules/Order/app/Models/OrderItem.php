<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Pharmacist;
use Modules\Drug\Models\Drug;
use Modules\Order\Database\factories\OrderItemFactory;
use Modules\Order\Services\FileService;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
       protected $fillable = [
        'order_id',
        'drug_id',
        'quantity',
        'price',
        'prescription_file',
    ];
    protected $hidden = [
    'created_at',
    'updated_at',
];
    
public function order()
{
    return $this->belongsTo(Order::class);
}

public function drug()
{
    return $this->belongsTo(Drug::class);
}
public function pharmacy()
{
    return $this->belongsTo(Pharmacist::class);
}
public function getPrescriptionUrlAttribute()
{
    return $this->prescription_file
        ? app(FileService::class)->url($this->prescription_file)
        : null;
}

}
