<?php
namespace Modules\Order\Services;

use App\Support\PublicMedia;
use Illuminate\Support\Facades\Storage;

class FileService
{
    public function upload($file, $folder = 'uploads', $disk = 'public')
    {
        if (!$file) return null;

        $path = $file->store($folder, $disk);
        if ($disk === 'public' && is_string($path)) {
            PublicMedia::publish($path);
        }

        return $path;
    }

    public function url($path, $disk = 'public')
    {
        if (!$path) return null;

        return PublicMedia::url($path);
    }

    public function delete($path, $disk = 'public')
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
