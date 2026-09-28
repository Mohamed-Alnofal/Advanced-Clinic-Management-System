<?php

namespace Modules\Drug\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dosage extends Model
{
    use HasFactory;

    protected $table = 'dosages';

    protected $fillable = [
        'dosage',
        'route',
        'strength',
        'drug_id',
    ];

   
    
    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

   

   
    public function getFullDosageAttribute()
    {
        return "{$this->dosage} - {$this->strength} ({$this->route})";
    }
}