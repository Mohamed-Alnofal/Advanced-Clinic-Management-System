<?php

namespace Modules\Laboratory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Doctors\Models\Doctor;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Models\Patients;
use Illuminate\Database\Eloquent\Relations\HasOne;
class LaboratoryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'patient_file_id',
        'medical_visit_id',
        'laboratory_id',
        'doctor_notes',
        'status',
        'rejection_reason',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /*
     * الطبيب الذي طلب التحاليل
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(
            Doctor::class,
            'doctor_id'
        );
    }

    /*
     * المريض
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            Patients::class,
            'patient_id'
        );
    }

    /*
     * الملف الطبي
     */
    public function patientFile(): BelongsTo
    {
        return $this->belongsTo(
            PatientFile::class,
            'patient_file_id'
        );
    }

    /*
     * الزيارة الطبية
     */
    public function medicalVisit(): BelongsTo
    {
        return $this->belongsTo(
            MedicalVisits::class,
            'medical_visit_id'
        );
    }

    /*
     * المخبر
     */
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(
            Laboratory::class,
            'laboratory_id'
        );
    }

    /*
     * التحاليل الموجودة داخل الطلب
     */
    public function items(): HasMany
    {
        return $this->hasMany(
            LaboratoryOrderItem::class,
            'laboratory_order_id'
        );
    }

    public function result(): HasMany
    {
        return $this->hasMany(
            LaboratoryResult::class,
            'laboratory_order_id'
        );
    }
    public function results()
    {
        return $this->hasMany(
            LaboratoryResult::class,
            'laboratory_order_id'
        );
    }
}