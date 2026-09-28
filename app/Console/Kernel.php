<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('appointments:send-reminders')->everyMinute();
        $schedule->command('appointments:expire-holds')->everyFiveMinutes();

        $offerTtl = max(1, (int) config('ambulance.dispatch.offer_ttl_minutes', 2));
        $escalate = max(1, (int) config('ambulance.dispatch.escalate_pending_after_minutes', 2));
        $pending = max($escalate, (int) config('ambulance.dispatch.expire_pending_after_minutes', 5));
        $assignedHours = max(1, (int) config('ambulance.dispatch.expire_assigned_after_hours', 6));

        // Every minute so short SOS TTLs (e.g. 5 min cancel) stay accurate.
        $schedule->command("ambulance:expire-offers --minutes={$offerTtl}")->everyMinute();
        $schedule->command(
            "ambulance:expire-stale-requests --escalate-minutes={$escalate} --pending-minutes={$pending} --assigned-hours={$assignedHours}"
        )->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
