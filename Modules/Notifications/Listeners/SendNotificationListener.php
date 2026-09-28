<?php

namespace Modules\Notifications\Listeners;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendNotificationListener
{

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle($event)
    {
        $this->notificationService->handle($event);
    }

}
