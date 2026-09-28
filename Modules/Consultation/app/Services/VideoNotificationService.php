<?php

namespace Modules\Consultation\Services;

use Modules\Consultation\Models\VideoSubscription;
use Modules\Notifications\Services\NotificationService;

class VideoNotificationService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * إرسال إشعار للمشتركين عند توفر مواعيد فيديو
     */
    public function notifyDoctorSubscribers($doctor, array $records): void
    {
        // لا ترسل إذا لم توجد مواعيد فيديو
        $hasVideo = collect($records)->contains(function ($item) {
            return $item['consultation_type'] === 'video';
        });

        if (!$hasVideo) {
            return;
        }

        $subscriptions = VideoSubscription::with('user')
            ->where('doctor_id', $doctor->id)
            ->get();

        foreach ($subscriptions as $subscription) {

            if (!$subscription->user) {
                continue;
            }

            $this->notificationService->sendToUser(

                $subscription->user,

                '📹 مواعيد فيديو جديدة',

                'قام الطبيب بإضافة مواعيد فيديو جديدة ويمكنك الحجز الآن.',

                [
                    'doctor_id' => $doctor->id,
                    'type' => 'video_available'
                ]

            );
        }
    }
}