<?php

namespace Modules\Laboratory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LaboratoryOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_order_id',
        'test_name',
        'doctor_note',
        'status',
    ];

    // protected $casts = [
    //     'started_at' => 'datetime',
    //     'completed_at' => 'datetime',
    // ];

    /*
     * الطلب الأساسي
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(
            LaboratoryOrder::class,
            'laboratory_order_id'
        );
    }

    /*
     * نتيجة التحليل
     */
    public function result(): HasOne
    {
        return $this->hasOne(
            LaboratoryResult::class,
            'laboratory_order_item_id'
        );
    }
}