<?php

namespace Modules\Consultation\Models;
use Illuminate\Database\Eloquent\Model;
use Modules\Doctors\Models\Doctor;
use Modules\Users\Models\User;

class VideoSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'doctor_id',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}