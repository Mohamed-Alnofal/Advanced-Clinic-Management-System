<?php

namespace Modules\HomeCare\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeCareSpecialty extends Model
{
    protected $table = 'home_care_specialties';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

  protected $casts = [
        'is_active' => 'boolean',
    ];

    public function profiles(): HasMany
    {
        return $this->hasMany(HomeCareProfile::class, 'specialty_id');
    }
}
