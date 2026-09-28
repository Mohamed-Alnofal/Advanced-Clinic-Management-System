<?php

namespace Modules\Subscription\Payments\Contracts;

use App\Contracts\Subscribable;
use Modules\Subscription\Models\Subscription;
use Modules\Subscription\Models\SubscriptionPlan;

interface PaymentMethod
{
    public function pay(
        Subscribable $subscriber,
        SubscriptionPlan $plan
    ): Subscription;
}