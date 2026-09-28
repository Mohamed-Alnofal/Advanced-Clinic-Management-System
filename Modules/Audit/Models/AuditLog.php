<?php

namespace Modules\Audit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Users\Models\User;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'user_email', 'user_name', 'role',
        'action', 'entity_type', 'entity_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent', 'url', 'method'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for filtering
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}