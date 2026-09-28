<?php

namespace Modules\Subscription\Payments\Manual;

use App\Contracts\Subscribable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Subscription\Models\Subscription;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Models\SubscriptionPayment;
use Modules\Subscription\Payments\Contracts\PaymentMethod;

class ManualPayment
{
    public function createPendingPayment(
        Subscribable $subscriber,
        SubscriptionPlan $plan,
        ?string $receipt = null
    ): Subscription {

        return DB::transaction(function () use (
            $subscriber,
            $plan,
            $receipt
        ) {

            if (!$plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' =>
                        'الخطة غير متاحة.',
                ]);
            }

            if (
                $plan->subscriber_type !==
                $subscriber->subscriptionType()
            ) {
                throw ValidationException::withMessages([
                    'plan_id' =>
                        'هذه الخطة غير مناسبة لنوع حسابك.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | منع وجود اشتراك فعال
            |--------------------------------------------------------------------------
            */

            $active =
                $subscriber
                    ->subscriptions()
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now())
                    ->exists();

            if ($active) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'لديك اشتراك فعال بالفعل.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | إنشاء Subscription Pending
            |--------------------------------------------------------------------------
            */

            $subscription =
                $subscriber
                    ->subscriptions()
                    ->create([
                        'subscription_plan_id' =>
                            $plan->id,

                        'status' =>
                            'pending',

                        'starts_at' =>
                            null,

                        'ends_at' =>
                            null,
                    ]);

            /*
            |--------------------------------------------------------------------------
            | إنشاء Payment Pending
            |--------------------------------------------------------------------------
            */

            SubscriptionPayment::create([
                'subscription_id' =>
                    $subscription->id,

                'amount' =>
                    $plan->price,

                'method' =>
                    'manual',

                'status' =>
                    'pending',

                'receipt' =>
                    $receipt,
            ]);

            return $subscription->load([
                'plan',
                'payments',
            ]);
        });
    }
}