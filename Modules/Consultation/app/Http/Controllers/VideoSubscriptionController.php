<?php

namespace Modules\Consultation\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Modules\Consultation\Models\VideoSubscription;

class VideoSubscriptionController extends Controller
{

    /**
     * اشتراك / إلغاء اشتراك
     */
    public function toggle($doctorId)
    {
        $user = auth()->user();

        $subscription = VideoSubscription::where('doctor_id', $doctorId)
            ->where('user_id', $user->id)
            ->first();

        if ($subscription) {

            $subscription->delete();

            return response()->json([
                'success' => true,
                'subscribed' => false,
                'message' => 'تم إلغاء الاشتراك'
            ]);
        }

        VideoSubscription::create([
            'doctor_id' => $doctorId,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'subscribed' => true,
            'message' => 'تم الاشتراك بنجاح'
        ]);
    }

    /**
     * هل المستخدم مشترك؟
     */
    public function status($doctorId)
    {
        return response()->json([

            'success' => true,

            'subscribed' => VideoSubscription::where(
                'doctor_id',
                $doctorId
            )
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->exists()

        ]);
    }
}