<?php

namespace Modules\Disputes\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Modules\Users\Models\User;

use Modules\Doctors\Models\Doctor;
use Modules\Pharmacist\Models\Pharmacist;

// use Modules\Pharmacists\Models\Pharmacist;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [

        'complainant_id',

        'defendant_id',

        'handled_by',

        'doctor_id',

        'pharmacist_id',

        'subject',

        'description',

        'priority',

        'status',

        'resolved_at',

        'is_escalated',
    ];

    protected $casts = [

        'resolved_at' => 'datetime',

        'is_escalated' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Complainant
    |--------------------------------------------------------------------------
    */
    public function complainant()
    {
        return $this->belongsTo(
            User::class,
            'complainant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Defendant
    |--------------------------------------------------------------------------
    */
    public function defendant()
    {
        return $this->belongsTo(
            User::class,
            'defendant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Handler
    |--------------------------------------------------------------------------
    */
    public function handler()
    {
        return $this->belongsTo(
            User::class,
            'handled_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Doctor
    |--------------------------------------------------------------------------
    */
    public function doctor()
    {
        return $this->belongsTo(
            Doctor::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pharmacist
    |--------------------------------------------------------------------------
    */
    public function pharmacist()
    {
        return $this->belongsTo(
            Pharmacist::class
        );
    }
}