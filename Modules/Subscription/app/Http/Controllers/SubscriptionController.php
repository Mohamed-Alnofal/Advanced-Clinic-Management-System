<?php

namespace Modules\Subscription\Http\Controllers;

// use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use App\Services\SubscribableResolver;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Services\SubscriptionService;
use Modules\Subscription\Http\Requests\SubscribeWalletRequest;
use Modules\Subscription\Http\Requests\SubscribeManualRequest;
use Modules\Subscription\Http\Requests\UpdateAutoRenewRequest;
use App\Support\PublicMedia;

class SubscriptionController
{
    public function __construct(
        private SubscriptionService $service,
        private SubscribableResolver $resolver
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    */

    public function plans(Request $request)
    {
        $subscriber =
            $this->resolver->resolve(
                $request->user()
            );

        $plans =
            SubscriptionPlan::query()
                ->where(
                    'subscriber_type',
                    $subscriber->subscriptionType()
                )
                ->where('is_active', true)
                ->orderBy('duration')
                ->get();

        return response()->json([
            'message' => 'Subscription plans.',
            'data' => $plans,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Wallet Subscription
    |--------------------------------------------------------------------------
    */

    public function subscribeWithWallet(
        SubscribeWalletRequest $request
    ) {
        $subscriber = $this->resolver->resolve(
            $request->user()
        );

        $plan = SubscriptionPlan::findOrFail(
            $request->integer('plan_id')
        );

        $subscription = $this->service->subscribeWithWallet(
            $subscriber,
            $plan,
            $request->boolean('auto_renew')
        );

        return response()->json([
            'message' => 'تم الاشتراك بنجاح.',
            'data' => $subscription,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Manual Subscription
    |--------------------------------------------------------------------------
    */

    public function subscribeManually(
        SubscribeManualRequest $request
    ) {

        $subscriber =
            $this->resolver->resolve(
                $request->user()
            );

        $plan =
            SubscriptionPlan::findOrFail(
                $request->integer('plan_id')
            );

        /*
        |--------------------------------------------------------------------------
        | Store receipt
        |--------------------------------------------------------------------------
        */

        $receipt = PublicMedia::store(
            $request->file('receipt'),
            'subscription-receipts'
        );

        $subscription =
            $this->service->subscribeManually(
                $subscriber,
                $plan,
                $receipt
            );

        return response()->json([
            'message' =>
                'تم إرسال طلب الاشتراك للمراجعة.',

            'data' =>
                $subscription,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Current Subscription
    |--------------------------------------------------------------------------
    */

    public function current(Request $request)
    {
        $subscriber =
            $this->resolver->resolve(
                $request->user()
            );

        $subscription =
            $this->service->current(
                $subscriber
            );

        return response()->json([
            'data' =>
                $subscription,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel
    |--------------------------------------------------------------------------
    */

    public function cancel(Request $request)
    {
        $subscriber =
            $this->resolver->resolve(
                $request->user()
            );

        $subscription =
            $this->service->cancelAtPeriodEnd(
                $subscriber
            );

        return response()->json([
            'message' =>
                'سيتم إلغاء الاشتراك عند نهاية الفترة.',

            'data' =>
                $subscription,
        ]);
    }

    public function updateAutoRenew(
    UpdateAutoRenewRequest $request
) {
    $subscriber = $this->resolver->resolve(
        $request->user()
    );

    $subscription = $this->service->updateAutoRenew(
        $subscriber,
        $request->boolean('auto_renew')
    );

    return response()->json([
        'message' => $request->boolean('auto_renew')
            ? 'تم تفعيل التجديد التلقائي.'
            : 'تم إيقاف التجديد التلقائي.',

        'data' => $subscription,
    ]);
}
}