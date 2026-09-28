<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Doctors\Models\Doctor;

class Governorate extends Model
{
    use HasFactory;

      protected $fillable = ['name'];
      
    public function doctors()
{
    return $this->hasMany(Doctor::class);
}

public function pharmacists()
{
    return $this->hasMany(Pharmacist::class);
}
}
