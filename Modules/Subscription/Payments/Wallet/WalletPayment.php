<?php

namespace Modules\Subscription\Payments\Wallet;

use App\Contracts\Subscribable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Subscription\Models\Subscription;
use Modules\Subscription\Models\SubscriptionPlan;
use Modules\Subscription\Models\SubscriptionPayment;
use Modules\Subscription\Payments\Contracts\PaymentMethod;
use Modules\Users\Models\User;
use Modules\Wallet\Models\Wallet;
use Modules\WalletTransaction\Models\WalletTransaction;

class WalletPayment implements PaymentMethod
{
    public function pay(
        Subscribable $subscriber,
        SubscriptionPlan $plan,
        bool $autoRenew = false
    ): Subscription {

        return DB::transaction(function () use (
            $subscriber,
            $plan,
            $autoRenew
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1. التحقق من الخطة
            |--------------------------------------------------------------------------
            */

            if (!$plan->is_active) {
                throw ValidationException::withMessages([
                    'plan_id' => 'الخطة غير متاحة.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 2. التحقق من نوع الخطة
            |--------------------------------------------------------------------------
            */

            if (
                $plan->subscriber_type !==
                $subscriber->subscriptionType()
            ) {
                throw ValidationException::withMessages([
                    'plan_id' => 'هذه الخطة غير مناسبة لنوع حسابك.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. منع الاشتراك المكرر
            |--------------------------------------------------------------------------
            */

            $hasActiveSubscription =
                $subscriber
                    ->subscriptions()
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now())
                    ->exists();

            if ($hasActiveSubscription) {
                throw ValidationException::withMessages([
                    'subscription' => 'لديك اشتراك فعال بالفعل.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 4. محفظة المشترك
            |--------------------------------------------------------------------------
            */

            $subscriberWallet = Wallet::where(
                'user_id',
                $subscriber->getSubscriptionUserId()
            )
                ->lockForUpdate()
                ->first();

            if (!$subscriberWallet) {
                throw ValidationException::withMessages([
                    'wallet' => 'لم يتم العثور على محفظة المشترك.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 5. محفظة الأدمن
            |--------------------------------------------------------------------------
            */

                    $admin = User::role('admin')->first();

            if (!$admin) {
                throw ValidationException::withMessages([
                    'wallet' => 'لم يتم العثور على حساب الأدمن.',
                ]);
            }

            $adminWallet = Wallet::where(
                'user_id',
                $admin->id
            )
                ->lockForUpdate()
                ->first();

            if (!$adminWallet) {
                throw ValidationException::withMessages([
                    'admin_wallet' => 'لم يتم العثور على محفظة الأدمن.',
                ]);
            }

            if (!$adminWallet) {
                throw ValidationException::withMessages([
                    'admin_wallet' => 'لم يتم العثور على محفظة الأدمن.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 6. فحص رصيد المشترك
            |--------------------------------------------------------------------------
            */

            if (
                bccomp(
                    (string) $subscriberWallet->balance,
                    (string) $plan->price,
                    2
                ) < 0
            ) {
                throw ValidationException::withMessages([
                    'wallet' => 'رصيد المحفظة غير كافٍ.',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 7. حساب التواريخ
            |--------------------------------------------------------------------------
            */

            $startsAt = now();

            $endsAt = match ($plan->duration) {

                'monthly' =>
                    $startsAt->copy()->addMonth(),

                'yearly' =>
                    $startsAt->copy()->addYear(),

                default =>
                    throw ValidationException::withMessages([
                        'plan_id' => 'مدة الاشتراك غير صحيحة.',
                    ]),
            };

            /*
            |--------------------------------------------------------------------------
            | 8. خصم المبلغ من محفظة المشترك
            |--------------------------------------------------------------------------
            */

            $subscriberWallet->decrement(
                'balance',
                $plan->price
            );

            /*
            |--------------------------------------------------------------------------
            | 9. إضافة المبلغ إلى محفظة الأدمن
            |--------------------------------------------------------------------------
            */

            $adminWallet->increment(
                'balance',
                $plan->price
            );

            /*
            |--------------------------------------------------------------------------
            | 10. إنشاء Subscription
            |--------------------------------------------------------------------------
            */

            $subscription =
                $subscriber
                    ->subscriptions()
                    ->create([
                        'subscription_plan_id' =>
                            $plan->id,

                        'starts_at' =>
                            $startsAt,

                        'ends_at' =>
                            $endsAt,

                        'status' =>
                            'active',

                        'auto_renew' =>
                            $autoRenew,

                        'cancel_at_period_end' =>
                            false,
                    ]);

            /*
            |--------------------------------------------------------------------------
            | 11. إنشاء Payment
            |--------------------------------------------------------------------------
            */

            $payment =
                SubscriptionPayment::create([
                    'subscription_id' =>
                        $subscription->id,

                    'amount' =>
                        $plan->price,

                    'method' =>
                        'wallet',

                    'status' =>
                        'paid',

                    'paid_at' =>
                        now(),
                ]);

            /*
            |--------------------------------------------------------------------------
            | 12. Transaction - خصم من المشترك
            |--------------------------------------------------------------------------
            */

            WalletTransaction::create([
                'wallet_id' =>
                    $subscriberWallet->id,

                'amount' =>
                    $plan->price,

                'type' =>
                    'withdraw',

                'status' =>
                    'completed',

                'reference_type' =>
                    SubscriptionPayment::class,

                'reference_id' =>
                    $payment->id,

                'description' =>
                    'Subscription payment',
            ]);

            /*
            |--------------------------------------------------------------------------
            | 13. Transaction - إيداع للأدمن
            |--------------------------------------------------------------------------
            */

            WalletTransaction::create([
                'wallet_id' =>
                    $adminWallet->id,

                'amount' =>
                    $plan->price,

                'type' =>
                    'deposit',

                'status' =>
                    'completed',

                'reference_type' =>
                    SubscriptionPayment::class,

                'reference_id' =>
                    $payment->id,

                'description' =>
                    'Subscription payment received',
            ]);

            /*
            |--------------------------------------------------------------------------
            | 14. إرجاع الاشتراك
            |--------------------------------------------------------------------------
            */

            return $subscription->load([
                'plan',
                'payments',
            ]);
        }, attempts: 3);
    }
}