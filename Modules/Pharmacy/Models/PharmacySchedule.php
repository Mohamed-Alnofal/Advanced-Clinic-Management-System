<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class PharmacySchedule extends Model
{
    use HasRoles;
    protected $fillable = [
        'pharmacy_id',
        'day',
        'open_time',
        'close_time',
        'is_closed',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}