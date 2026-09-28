<?php

namespace Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Appointments\Models\Appointment;

class WalletHold extends Model
{
    protected $fillable = [
        'wallet_id',
        'appointment_id',
        'wallet_transaction_id',
        'amount',
        'status',
        'idempotency_key',
        'expires_at',
        'released_at',
        'captured_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'captured_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
