<?php

namespace Modules\Price\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Drug\Models\Drug;
use Modules\Price\Database\factories\PriceFactory;

class Price extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];
    
      public function drug()
    {
        return $this->belongsTo(Drug::class);
    }
}
