<?php

namespace Modules\Order\Observers;

use App\Jobs\SendNotificationJob;
use Modules\Order\Models\Order;

class OrderObserver
{
    /**
     * عند إنشاء طلب جديد
     */
        public function created(Order $order): void
        {
            $order->loadMissing(['pharmacy']);
            
            if ($order->pharmacy && !$order->pharmacist_id) {
                $order->pharmacist_id = $order->pharmacy->pharmacist_id;
                $order->saveQuietly(); 
            }
            
            $order->loadMissing(['pharmacist.user', 'user']);
            
            if ($order->pharmacist && $order->pharmacist->user) {
                $this->notifyPharmacistNewRequest($order);
            }
        }

    /**
     * عند تحديث الطلب
     */
    public function updated(Order $order): void
    {
        // ✅ تحميل العلاقات المطلوبة
        $order->loadMissing(['user']);
        
        if ($order->isDirty('status')) {
            
            if ($order->status === 'accepted') {
                $this->notifyRequestConfirmed($order);
            }
            
            if ($order->status === 'cancelled') {
                $this->notifyRequestCancelled($order);
            }
            
            if ($order->status === 'rejected') {
                $this->notifyRequestRejected($order);
            }
        }
    }

    /**
     * 🟢 إشعار الصيدلي بطلب جديد
     */
    private function notifyPharmacistNewRequest(Order $order): void
    {
        // ✅ التحقق من وجود الصيدلي والمستخدم
        if ($order->pharmacist && $order->pharmacist->user) {
            SendNotificationJob::dispatch(
                $order->pharmacist->user,
                'drug_request',
                'طلب دواء جديد 💊',
                'قام مريض بطلب دواء جديد',
                [
                    'order_id' => $order->id,
                    'total_price' => $order->total_price
                ]
            );
        }
    }

    /**
     * 🟡 إشعار المريض بقبول الطلب
     */
    private function notifyRequestConfirmed(Order $order): void
    {
        if ($order->user) {
            SendNotificationJob::dispatch(
                $order->user,
                'drug_request',
                'تم قبول الطلب ✅',
                'تم قبول طلب الدواء الخاص بك وسيتم تجهيزه',
                [
                    'order_id' => $order->id
                ]
            );
        }
    }

    /**
     * 🔴 إشعار المريض بإلغاء الطلب
     */
    private function notifyRequestCancelled(Order $order): void
    {
        if ($order->user) {
            SendNotificationJob::dispatch(
                $order->user,
                'drug_request',
                'تم إلغاء الطلب ❌',
                'تم إلغاء طلب الدواء الخاص بك',
                [
                    'order_id' => $order->id
                ]
            );
        }
    }

    /**
     * ❌ إشعار المريض برفض الطلب
     */
    private function notifyRequestRejected(Order $order): void
    {
        if ($order->user) {
            SendNotificationJob::dispatch(
                $order->user,
                'drug_request',
                'تم رفض الطلب ❌',
                'عذراً، تم رفض طلب الدواء الخاص بك',
                [
                    'order_id' => $order->id,
                    'reason' => $order->rejection_reason ?? 'لم يتم تحديد سبب'
                ]
            );
        }
    }
}