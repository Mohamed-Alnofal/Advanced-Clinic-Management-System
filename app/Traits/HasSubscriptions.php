<?php

namespace App\Traits;

use Modules\Subscription\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSubscriptions
{
    /**
     * جميع اشتراكات المشترك.
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(
            Subscription::class,
            'subscriber'
        );
    }

    /**
     * الاشتراك الحالي الفعال.
     */
    public function activeSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    /**
     * هل لديه اشتراك فعال؟
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->exists();
    }
}