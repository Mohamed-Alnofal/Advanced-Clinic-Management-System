<?php

namespace Modules\Subscription\Services;

use App\Contracts\Subscribable;
use Modules\Subscription\Models\Subscription;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Payments\Wallet\WalletPayment;
use Modules\Subscription\Payments\Manual\ManualPayment;
use Illuminate\Validation\ValidationException;
class SubscriptionService
{
    public function __construct(
        private WalletPayment $walletPayment,
        private ManualPayment $manualPayment,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Wallet
    |--------------------------------------------------------------------------
    */

    public function subscribeWithWallet(
        Subscribable $subscriber,
        SubscriptionPlan $plan,
        bool $autoRenew = false
    ): Subscription {

        return $this->walletPayment->pay(
            $subscriber,
            $plan,
            $autoRenew
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Manual
    |--------------------------------------------------------------------------
    */

    public function subscribeManually(
        Subscribable $subscriber,
        SubscriptionPlan $plan,
        ?string $receipt = null
    ): Subscription {

        return $this->manualPayment
            ->createPendingPayment(
                $subscriber,
                $plan,
                $receipt
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Current Subscription
    |--------------------------------------------------------------------------
    */

    public function current(
        Subscribable $subscriber
    ): ?Subscription {

        return $subscriber
            ->subscriptions()
            ->with([
                'plan',
                'payments',
            ])
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel
    |--------------------------------------------------------------------------
    */

    public function cancelAtPeriodEnd(
        Subscribable $subscriber
    ): Subscription {

        $subscription =
            $subscriber
                ->subscriptions()
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->latest('ends_at')
                ->first();

        abort_if(
            !$subscription,
            404,
            'لا يوجد اشتراك فعال.'
        );

        $subscription->update([
            'cancel_at_period_end' => true,
        ]);

        return $subscription->fresh([
            'plan',
            'payments',
        ]);
    }

    public function updateAutoRenew(
    Subscribable $subscriber,
    bool $autoRenew
): Subscription {

    $subscription = $subscriber
        ->subscriptions()
        ->where('status', 'active')
        ->where('starts_at', '<=', now())
        ->where('ends_at', '>', now())
        ->latest()
        ->first();

    if (!$subscription) {
        throw ValidationException::withMessages([
            'subscription' => 'لا يوجد اشتراك فعال.',
        ]);
    }

    $subscription->update([
        'auto_renew' => $autoRenew,

        // إذا فعل التجديد التلقائي نلغي إلغاء نهاية الفترة
        'cancel_at_period_end' => !$autoRenew,
    ]);

    return $subscription->fresh([
        'plan',
        'payments',
    ]);
}

/*
|--------------------------------------------------------------------------
| Admin - Subscription Plans
|--------------------------------------------------------------------------
*/

public function getAllPlans()
{
    return SubscriptionPlan::query()
        ->orderBy('subscriber_type')
        ->orderBy('duration')
        ->latest()
        ->get();
}

public function createPlan(array $data): SubscriptionPlan
{
    return SubscriptionPlan::create($data);
}

public function updatePlan(
    SubscriptionPlan $plan,
    array $data
): SubscriptionPlan {
    $plan->update($data);

    return $plan->fresh();
}

public function deletePlan(
    SubscriptionPlan $plan
): void {
    if ($plan->subscriptions()->exists()) {
        throw ValidationException::withMessages([
            'plan' =>
                'لا يمكن حذف الخطة لأنها مرتبطة باشتراكات.',
        ]);
    }

    $plan->delete();
}

public function togglePlanStatus(
    SubscriptionPlan $plan
): SubscriptionPlan {
    $plan->update([
        'is_active' => !$plan->is_active,
    ]);

    return $plan->fresh();
}
}