<?php

namespace Modules\Order\app\Listeners;

use Modules\Order\app\Events\OrderStatusChanged;
use Modules\Order\app\Jobs\SendOrderStatusNotificationJob;

class SendOrderStatusNotificationListener
{
    public function handle(OrderStatusChanged $event): void
    {
        SendOrderStatusNotificationJob::dispatch(
            $event->order,
            $event->status
        );
    }
}