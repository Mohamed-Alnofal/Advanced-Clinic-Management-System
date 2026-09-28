<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Hostinger (and similar shared hosts) often disable symlink(), so
 * `php artisan storage:link` cannot expose storage/app/public.
 * We keep writing to the public disk and mirror files into a real
 * public/storage directory that Apache can serve.
 */
class PublicMedia
{
    public static function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store(trim($directory, '/'), 'public');
        self::publish($path);

        return $path;
    }

    public static function publish(?string $relativePath): void
    {
        if (! is_string($relativePath) || $relativePath === '') {
            return;
        }

        $relativePath = self::normalizeRelative($relativePath);
        if ($relativePath === null) {
            return;
        }

        $source = storage_path('app/public/'.$relativePath);
        if (! is_file($source)) {
            return;
        }

        foreach (self::publicRoots() as $root) {
            $dest = rtrim($root, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR
                .str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $dir = dirname($dest);
            if (! is_dir($dir)) {
                File::ensureDirectoryExists($dir, 0755);
            }
            if (! is_file($dest) || filesize($dest) !== filesize($source)) {
                @copy($source, $dest);
            }
        }
    }

    public static function url(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $value = trim($path);
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $relative = self::normalizeRelative($value);
        if ($relative === null) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.$relative;
    }

    public static function absolutePath(string $relativePath): ?string
    {
        $relativePath = self::normalizeRelative($relativePath);
        if ($relativePath === null) {
            return null;
        }

        foreach (self::publicRoots() as $root) {
            $full = rtrim($root, DIRECTORY_SEPARATOR)
                .DIRECTORY_SEPARATOR
                .str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($full)) {
                return $full;
            }
        }

        $legacy = storage_path('app/public/'.$relativePath);
        if (is_file($legacy)) {
            return $legacy;
        }

        return null;
    }

    public static function syncAll(): int
    {
        $base = storage_path('app/public');
        if (! is_dir($base)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
            self::publish($relative);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public static function publicRoots(): array
    {
        $roots = [public_path('storage')];
        $extra = config('filesystems.public_mirror_path');
        if (is_string($extra) && $extra !== '') {
            $roots[] = $extra;
        }

        return array_values(array_unique($roots));
    }

    private static function normalizeRelative(string $path): ?string
    {
        $value = str_replace('\\', '/', ltrim($path, '/'));
        if (str_starts_with($value, 'storage/')) {
            $value = substr($value, strlen('storage/'));
        }
        if ($value === '' || str_contains($value, '..')) {
            return null;
        }

        return $value;
    }
}
