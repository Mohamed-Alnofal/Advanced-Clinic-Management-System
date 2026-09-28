<?php

namespace Modules\Subscription\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Subscription\Models\Subscription;
use Illuminate\Validation\Rule;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Services\SubscriptionService;
class AdminSubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  /*
    |--------------------------------------------------------------------------
    | Approve Manual Payment
    |--------------------------------------------------------------------------
    */

    public function __construct(
    private SubscriptionService $service
) {}
    public function approve(
        Subscription $subscription
    ) {

        return DB::transaction(function () use (
            $subscription
        ) {

            $subscription =
                Subscription::query()
                    ->with([
                        'plan',
                        'subscriber',
                    ])
                    ->lockForUpdate()
                    ->findOrFail(
                        $subscription->id
                    );

            /*
            |--------------------------------------------------------------------------
            | Check pending payment
            |--------------------------------------------------------------------------
            */

            $payment =
                $subscription
                    ->payments()
                    ->where('method', 'manual')
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'لا يوجد دفع يدوي معلق.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Check active subscription
            |--------------------------------------------------------------------------
            */

            $hasActive =
                $subscription
                    ->subscriber
                    ->subscriptions()
                    ->where('status', 'active')
                    ->where(
                        'id',
                        '!=',
                        $subscription->id
                    )
                    ->where(
                        'ends_at',
                        '>',
                        now()
                    )
                    ->exists();

            if ($hasActive) {
                throw ValidationException::withMessages([
                    'subscription' =>
                        'المستخدم لديه اشتراك فعال بالفعل.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */

            $startsAt = now();

            $endsAt = match (
                $subscription->plan->duration
            ) {
                'monthly' =>
                    $startsAt->copy()->addMonth(),

                'yearly' =>
                    $startsAt->copy()->addYear(),

                default =>
                    throw ValidationException::withMessages([
                        'plan' =>
                            'مدة الاشتراك غير صحيحة.',
                    ]),
            };

            /*
            |--------------------------------------------------------------------------
            | Activate
            |--------------------------------------------------------------------------
            */

            $subscription->update([
                'starts_at' =>
                    $startsAt,

                'ends_at' =>
                    $endsAt,

                'status' =>
                    'active',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Mark payment paid
            |--------------------------------------------------------------------------
            */

            $payment->update([
                'status' =>
                    'paid',

                'paid_at' =>
                    now(),
            ]);

            return response()->json([
                'message' =>
                    'تمت الموافقة على الاشتراك.',

                'data' =>
                    $subscription->fresh([
                        'plan',
                        'payments',
                        'subscriber',
                    ]),
            ]);
        }, attempts: 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Reject
    |--------------------------------------------------------------------------
    */

    public function reject(
        Subscription $subscription
    ) {

        return DB::transaction(function () use (
            $subscription
        ) {

            $payment =
                $subscription
                    ->payments()
                    ->where('method', 'manual')
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

            if (!$payment) {
                throw ValidationException::withMessages([
                    'payment' =>
                        'لا يوجد دفع يدوي معلق.',
                ]);
            }

            $payment->update([
                'status' =>
                    'rejected',
            ]);

            $subscription->update([
                'status' =>
                    'cancelled',
            ]);

            return response()->json([
                'message' =>
                    'تم رفض طلب الاشتراك.',
            ]);
        }, attempts: 3);
    }

    /*
|--------------------------------------------------------------------------
| Admin - Plans
|--------------------------------------------------------------------------
*/

public function plans()
{
    return response()->json([
        'message' => 'تم جلب خطط الاشتراك.',
        'data' => $this->service->getAllPlans(),
    ]);
}

public function storePlan(Request $request)
{
    $data = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
        ],

      'subscriber_type' => [
    'required',
    'string',
    'max:100',
],

        'duration' => [
            'required',
            Rule::in([
                'monthly',
                'yearly',
            ]),
        ],

        'price' => [
            'required',
            'numeric',
            'min:0',
        ],

        'is_active' => [
            'sometimes',
            'boolean',
        ],
    ]);

    $plan = $this->service->createPlan($data);

    return response()->json([
        'message' => 'تم إنشاء خطة الاشتراك بنجاح.',
        'data' => $plan,
    ], 201);
}

public function updatePlan(
    Request $request,
    SubscriptionPlan $subscriptionPlan
) {
    $data = $request->validate([
        'name' => [
            'sometimes',
            'string',
            'max:255',
        ],

      'subscriber_type' => [
    'sometimes',
    'string',
    'max:100',
],

        'duration' => [
            'sometimes',
            Rule::in([
                'monthly',
                'yearly',
            ]),
        ],

        'price' => [
            'sometimes',
            'numeric',
            'min:0',
        ],

        'is_active' => [
            'sometimes',
            'boolean',
        ],
    ]);

    $plan = $this->service->updatePlan(
        $subscriptionPlan,
        $data
    );

    return response()->json([
        'message' => 'تم تحديث خطة الاشتراك.',
        'data' => $plan,
    ]);
}

public function deletePlan(
    SubscriptionPlan $subscriptionPlan
) {
    $this->service->deletePlan(
        $subscriptionPlan
    );

    return response()->json([
        'message' => 'تم حذف خطة الاشتراك.',
    ]);
}

public function togglePlanStatus(
    Request $request,
    SubscriptionPlan $subscriptionPlan
) {
    $data = $request->validate([
        'is_active' => ['sometimes', 'boolean'],
    ]);

    $plan = array_key_exists('is_active', $data)
        ? $this->service->updatePlan($subscriptionPlan, [
            'is_active' => (bool) $data['is_active'],
        ])
        : $this->service->togglePlanStatus($subscriptionPlan);

    return response()->json([
        'message' => $plan->is_active
            ? 'تم تفعيل الخطة.'
            : 'تم تعطيل الخطة.',

        'data' => $plan,
    ]);
}
}
