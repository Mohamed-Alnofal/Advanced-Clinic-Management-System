<?php


namespace Modules\Pharmacist\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Drug\Models\Drug;
use Modules\MedicalFacility\Models\MedicalFacility;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Pharmacist\States\InterfaceFactory\PharmacyStateFactory;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use App\Contracts\Subscribable;
use App\Traits\HasSubscriptions;
class Pharmacist extends Model implements Subscribable
{
        use HasSubscriptions;

    protected $fillable = [
        'user_id',
        'pharmacy_name',
        'license_number',
        'image',
        'clinic_latitude',
        'clinic_longitude',
        'clinic_address',
        'governorate_id',
        'is_verified',
    ];

        public function user()
        {
            return $this->belongsTo(User::class);
        }
    public function drugs()
    {
         return $this->belongsToMany(
            Drug::class,
            'drug_pharmacist',
            'pharmacist_id',
            'drug_id'
        );
    }

  public function orders()
{
    return $this->hasMany(Order::class, 'pharmacist_id');
}

    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }
            public function state()
    {
        return PharmacyStateFactory::make($this->status);
    }
        public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

        public function medicalCenter()
    {
        return $this->belongsTo(MedicalFacility::class, 'medical_facility_id');
    }

    public function subscriptionType(): string
    {
        return 'pharmacist';
    }

    public function getSubscriptionUserId(): int
    {
        return $this->user_id;
    }

    /**
     * Pharmacists patients can order from: active subscription (same gate as order accept).
     */
    public function scopeListedForPatients($query)
    {
        return $query->whereHas('subscriptions', function ($q) {
            $q->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now());
        });
    }

    public function isListedForPatients(): bool
    {
        return $this->hasActiveSubscription();
    }
}