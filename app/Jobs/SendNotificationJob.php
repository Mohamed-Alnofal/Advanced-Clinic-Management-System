<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifications\Services\NotificationService;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $notifiable;
    public string $type;
    public string $title;
    public string $body;
    public array $data;

    public function __construct($notifiable, $type, $title, $body, $data = [])
    {
        $this->notifiable = $notifiable;
        $this->type = $type;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    public function handle(): void
    {
        app(NotificationService::class)->sendToUser(
            $this->notifiable,
            $this->title,
            $this->body,
            $this->data
        );
    }
}