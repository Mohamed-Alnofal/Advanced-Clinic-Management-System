<?php


namespace Modules\Notifications\Listeners;

use App\Events\UserRejected;
use Illuminate\Support\Facades\Mail;

class SendRejectionEmail
{
    public function handle(UserRejected $event)
    {
        $user = $event->user;
        $reason = $event->reason;

        Mail::raw(
            "Your account has been rejected.\nReason: $reason",
            function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Account Rejected');
            }
        );
    }
}