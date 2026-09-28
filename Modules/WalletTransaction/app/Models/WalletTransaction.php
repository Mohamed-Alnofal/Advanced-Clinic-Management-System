<?php

namespace Modules\WalletTransaction\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Database\factories\WalletTransactionFactory;

class WalletTransaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'status',
        'stripe_session_id',
        'idempotency_key',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'stripe_session_id'
    ];
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
    
  
}
