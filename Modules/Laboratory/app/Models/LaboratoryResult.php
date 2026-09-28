<?php

namespace Modules\Laboratory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
class LaboratoryResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_order_id',
        'result_file',
        'original_file_name',
    ];

        protected $hidden = [
        'result_file',
    ];

    protected $appends = [
        'file_url',
    ];

    public function laboratoryOrder(): BelongsTo
    {
        return $this->belongsTo(
            LaboratoryOrder::class,
            'laboratory_order_id'
        );
    
        }
            public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url(
            $this->result_file
        );
    }
}