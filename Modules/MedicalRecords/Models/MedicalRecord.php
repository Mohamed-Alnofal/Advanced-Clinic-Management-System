<?php

namespace Modules\MedicalRecords\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Users\Models\User;

class MedicalRecord extends Model
{
    protected $fillable = [
        'patient_id',
        'medical_visit_id',
        'uploaded_by',
        'title',
        'description',
        'file_path',
        'mime_type',
        'file_name',
        'status',
        'report',
        'extracted_data',
        'analyzed_at',
        'error_message',
    ];

    protected $casts = [
        'report' => 'array',
        'extracted_data' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function medicalVisit()
    {
        return $this->belongsTo(\Modules\MedicalVisits\Models\MedicalVisits::class, 'medical_visit_id');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}