<?php

namespace App\Console\Commands;

use App\Support\PublicMedia;
use Illuminate\Console\Command;

class SyncPublicStorageCommand extends Command
{
    protected $signature = 'storage:sync-public';

    protected $description = 'Copy storage/app/public files into public/storage (no symlink required)';

    public function handle(): int
    {
        foreach (PublicMedia::publicRoots() as $root) {
            if (! is_dir($root)) {
                @mkdir($root, 0755, true);
            }
            $this->line('Mirror root: '.$root);
        }

        $count = PublicMedia::syncAll();
        $this->info("Synced {$count} file(s) for public HTTP access.");

        return self::SUCCESS;
    }
}
