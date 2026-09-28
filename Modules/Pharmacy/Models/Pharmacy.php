<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Drug\Models\Drug;
use Modules\Pharmacy\Models\PharmacySchedule;
use Modules\Users\Models\User;
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Pharmacist\Models\Pharmacist;
use Spatie\Permission\Traits\HasRoles;
use Modules\Governorates\Models\Governorates;
use Modules\Order\Models\Order;

class Pharmacy extends Model
{
    use HasFactory , HasRoles;

    protected $table = 'pharmacies';

    protected $fillable = [
    'pharmacist_id',
    'medical_facility_id',
    'governorate_id',
];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // صاحب الصيدلية (المستخدم)
    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class);
    }

    // الصيدلية مرتبطة بمنشأة طبية
    public function medicalFacility()
    {
        return $this->belongsTo(MedicalFacility::class);
    }
    public function medicalCenter()
    {
        return $this->belongsTo(MedicalFacility::class, 'medical_facility_id');
    }
      public function schedules()
    {
        return $this->hasMany(PharmacySchedule::class);
    }
    public function drugs()
    {
        return $this->belongsToMany(\Modules\Drug\Models\Drug::class, 'pharmacy_drug')
                    ->using(PharmacyDrug::class)
                    ->withPivot('price', 'quantity', 'is_available')
                    ->withTimestamps();
    }

     public function orders()
    {
        return $this->hasMany(Order::class, 'pharmacy_id');
    }

    public function pharmacyDrugs()
    {
        return $this->hasMany(PharmacyDrug::class);
    }

    public function user()
{
    return $this->belongsTo(User::class);
}
}