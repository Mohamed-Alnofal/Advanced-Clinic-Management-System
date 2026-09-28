<?php
namespace Modules\Order\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public $order,
        public string $status
    ) {}

    public function handle(): void
    {
        $user = $this->order->user;

        $title = $this->status === 'accepted'
            ? 'Order Accepted'
            : 'Order Rejected';

        $body = $this->status === 'accepted'
            ? 'Your order #' . $this->order->id . ' has been accepted'
            : 'Your order #' . $this->order->id . ' has been rejected';

        // 1. حفظ في قاعدة البيانات
        $user->notifications()->create([
            'title' => $title,
            'body' => $body,
            'data' => json_encode([
                'order_id' => $this->order->id,
                'status' => $this->status
            ]),
        ]);

        // 2. Firebase Push
        if ($user->fcm_token) {
            $this->sendFirebase($user->fcm_token, $title, $body);
        }
    }

    private function sendFirebase($token, $title, $body)
    {
        // Firebase HTTP v1 API
    }
}