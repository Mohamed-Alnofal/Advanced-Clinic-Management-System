<?php

namespace Modules\Consultation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Appointments\Models\Appointment;
use Modules\Users\Models\User;
class Consultation extends Model
{
    use HasFactory;

    protected $table = 'consultations';

    protected $fillable = [
        'appointment_id',
        'room_name',
        'livekit_room_sid',
        'status',
        'started_at',
        'ended_at',
        'rating',
        'doctor_notes',
        'recording_url',
    ];

    protected $hidden = [
        'livekit_room_sid',
        'doctor_notes',
        'recording_url',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'rating'     => 'integer',
        'livekit_room_sid' => 'encrypted',
        'doctor_notes' => 'encrypted',
        'recording_url' => 'encrypted',
    ];

    /**
     * Appointment Relationship
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get Patient Through Appointment
     */
    public function patient()
    {
        return $this->appointment?->user;
    }

    /**
     * Get Doctor Through Appointment
     */
    public function doctor()
    {
        return $this->appointment?->doctor;
    }

    public function videoAudits()
    {
        return $this->hasMany(VideoAudit::class, 'consultation_id');
    }

    /**
     * Check if consultation is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if consultation completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public static function generateRoomName(): string
    {
        return 'room_' . str_replace('-', '', (string) \Str::uuid());
    }
}