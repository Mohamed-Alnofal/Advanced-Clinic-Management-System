<?php

namespace Modules\Laboratory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Users\Models\User;

class Laboratory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'name',
        'image',
        'phone',
        'address',
        'latitude',
        'longitude',
        'is_verified',
        'is_active',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

        public function orders(): HasMany
    {
        return $this->hasMany(
            LaboratoryOrder::class
        );
    }
}