<?php

namespace Modules\Consultation\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Users\Models\User;
class VideoAudit extends Model
{
   protected $table = 'video_audits';

    protected $fillable = [
        'consultation_id',
        'appointment_id',
        'user_id',
        'user_role',
        'user_name',
        'user_email',
        'event',
        'joined_at',
        'left_at',
        'duration_seconds',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'details',
    ];
    
   protected $casts = [
        'details' => 'array',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function appointment()
    {
        return $this->belongsTo(\Modules\Appointments\Models\Appointment::class, 'appointment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    /**
     * نطاق للبحث حسب المستخدم
     */
    public function scopeUserRole($query, $role)
    {
        return $query->where('user_role', $role);
    }

    /**
     * نطاق للبحث حسب الفترة الزمنية
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}