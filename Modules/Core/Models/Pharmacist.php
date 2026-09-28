<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Users\Models\User;

class Pharmacist extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'image',
        'governorate_id',
        'pharmacy_name',
        'license_number',
        'clinic_latitude',
        'clinic_longitude',
        'clinic_address',
        'is_verified'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    

    //     public function getCertificateUrlAttribute()
    // {
    //     return asset('storage/' . $this->medical_certificate);
    // }
}
