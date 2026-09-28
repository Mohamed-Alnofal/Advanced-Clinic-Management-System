<?php

namespace Modules\Attachments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Attachments\Database\factories\AttachmentsFactory;
use Modules\MedicalVisits\Models\MedicalVisits;
use Illuminate\Support\Facades\Storage;
use App\Support\PublicMedia;
class Attachments extends Model
{
    use HasFactory;
    protected $fillable = [
        'medical_visit_id',
        'file',
        'type',
  
    ];
    protected $appends = ['file_url'];

protected $hidden = [
    'created_at',
    'updated_at',
];
    public function medicalVisit()
    {
        return $this->belongsTo(MedicalVisits::class);
    }
    public function getFileUrlAttribute()
{
    return PublicMedia::url($this->file);
}
}
