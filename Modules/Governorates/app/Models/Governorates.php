<?php

namespace Modules\Governorates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Governorates\Database\factories\GovernoratesFactory;
use Modules\MedicalFacility\Models\MedicalFacility;

class Governorates extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name'];
    
    public function medicalFacilities()
    {
        return $this->hasMany(MedicalFacility::class);
    }
}
