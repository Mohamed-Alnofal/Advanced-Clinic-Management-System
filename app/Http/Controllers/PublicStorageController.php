<?php

namespace App\Http\Controllers;

use App\Support\PublicMedia;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'htaccess', 'env'];

    public function show(string $path): BinaryFileResponse
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            abort(404);
        }

        $full = PublicMedia::absolutePath($path);
        if ($full === null) {
            abort(404);
        }

        return response()->file($full);
    }
}
