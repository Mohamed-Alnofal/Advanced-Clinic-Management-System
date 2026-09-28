<?php

namespace Modules\Prescriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Prescriptions\Database\factories\PrescriptionItemFactory;

class PrescriptionItem extends Model
{
    use HasFactory;

 protected $fillable = [
        'prescription_id',
        'medicine_name',
        'dose',
        'frequency',
        'duration',
        'instructions',
    ];
protected $hidden = [
    'created_at',
    'updated_at',
];
    public function prescription()
    {
        return $this->belongsTo(Prescriptions::class);
    }
}
