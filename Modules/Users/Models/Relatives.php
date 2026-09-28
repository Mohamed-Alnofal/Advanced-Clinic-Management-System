<?php

namespace Modules\Users\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Patients\Models\Patients;

class Relatives extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'age',
        'gender',
    ];

    protected $hidden = [
        'name',
        'age',
        'gender',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'name' => 'encrypted',
        'age' => 'encrypted',
        'gender' => 'encrypted',
    ];

    // 👤 owner
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function patient()
    {
        return $this->morphOne(Patients::class,'patientable');
    }
}
