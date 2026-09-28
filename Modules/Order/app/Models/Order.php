<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Models\Pharmacist;
use Modules\Drug\Models\Drug;
// use Modules\Drug\Models\Drug;
use Modules\Order\Database\factories\OrderFactory;
use Modules\Order\Services\FileService;
use Modules\Users\Models\User;
use Modules\Pharmacy\Models\Pharmacy;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pharmacist_id',
        'pharmacy_id',
        // 'drug_id',
        'quantity',
        // 'delivery_address',
        // 'latitude',
        // 'longitude',
        'status',
        'payment_status',
        // 'prescription_file',
        'total_price',
        'locked_at',
    ];
    protected $hidden = [
    'created_at',
    'updated_at',
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

 public function pharmacist()
{
    return $this->belongsTo(Pharmacist::class, 'pharmacist_id');
}
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getPrescriptionFileUrlAttribute()
{
    return $this->prescription_file
        ? app(FileService::class)->url($this->prescription_file)
        : null;
}
public function pharmacists()
{
    return $this->belongsTo(Pharmacist::class);
}
}
