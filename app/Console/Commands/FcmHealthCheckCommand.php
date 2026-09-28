<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\FcmService;
use Modules\Core\Support\FirebaseCredentials;
use Modules\Notifications\Services\FirebaseService as NotificationsFirebaseService;
use Modules\Users\Models\FcmToken;
use Modules\Users\Models\User;

class FcmHealthCheckCommand extends Command
{
    protected $signature = 'fcm:health
        {--user= : Optional user id to inspect tokens for}
        {--send : Send a test push to the first token of --user}';

    protected $description = 'Verify Firebase credentials, FCM token storage, and optional test send';

    public function handle(): int
    {
        $this->info('FCM health check');

        try {
            $path = FirebaseCredentials::path();
            $this->line('Credentials: '.$path);
            $this->line('Project: '.FirebaseCredentials::projectId());
        } catch (\Throwable $e) {
            $this->error('Credentials FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            app(NotificationsFirebaseService::class);
            $this->info('Notifications FirebaseService: OK');
        } catch (\Throwable $e) {
            $this->error('Notifications FirebaseService FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            // Resolve service without requiring a real user write.
            app(FcmService::class);
            $this->info('FcmService resolve: OK');
        } catch (\Throwable $e) {
            $this->error('FcmService resolve FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        $userId = $this->option('user');
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->warn("User {$userId} not found");
            } else {
                $tokens = FcmToken::where('user_id', $userId)->pluck('token');
                $this->line("User {$userId} ({$user->email}) tokens: ".$tokens->count());
                if ($tokens->isEmpty()) {
                    $this->warn('No FCM tokens — open the app, allow notifications, login again.');
                } elseif ($this->option('send')) {
                    try {
                        app(NotificationsFirebaseService::class)->sendToToken(
                            $tokens->first(),
                            'ACMS FCM test',
                            'External push is working.',
                            ['type' => 'test']
                        );
                        $this->info('Test push sent.');
                    } catch (\Throwable $e) {
                        $this->error('Test push FAILED: '.$e->getMessage());

                        return self::FAILURE;
                    }
                }
            }
        } else {
            $this->line('Total stored device tokens: '.FcmToken::count());
        }

        $this->info('Health check complete.');

        return self::SUCCESS;
    }
}
