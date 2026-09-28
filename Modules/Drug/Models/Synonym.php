<?php

namespace Modules\Drug\Models;

use Illuminate\Database\Eloquent\Model;

class Synonym extends Model
{
    protected $table = 'synonyms'; // تأكد من اسم الجدول في قاعدة البيانات

    protected $fillable = [
        'synonym',
        'drug_id',
    ];

    /**
     * الدواء المرتبط
     */
    public function drug()
    {
        return $this->belongsTo(Drug::class, 'drug_id');
    }
}