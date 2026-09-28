<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Modules\Notifications\Database\factories\NotificationsFactory;

class Notifications extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['type', 'notifiable_id', 'notifiable_type', 'data', 'read_at'];
    
        protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        
    ];

        /**
         * Disable incrementing for UUID primary key and set key type.
         */
        public $incrementing = false;
        protected $keyType = 'string';

        protected static function boot(): void
        {
            parent::boot();

            static::creating(function ($model) {
                if (empty($model->{$model->getKeyName()})) {
                    $model->{$model->getKeyName()} = (string) Str::uuid();
                }
            });
        }

        public function notifiable()
        {
            return $this->morphTo();
        }

        public function isRead(): bool
        {
            return $this->read_at !== null;
        }

          public function markAsRead(): void
        {
            $this->update([
                'read_at' => now()
            ]);
        }



}
