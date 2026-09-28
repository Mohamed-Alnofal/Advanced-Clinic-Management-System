<?php

namespace Modules\Core\Support;

use RuntimeException;

/**
 * Single source of truth for Firebase Admin SDK credential file resolution.
 */
final class FirebaseCredentials
{
    public static function path(): string
    {
        $configured = (string) env('FIREBASE_CREDENTIALS', 'app/firebase/serviceAccountKey.json');
        $configured = str_replace('\\', '/', trim($configured));

        $candidates = [];

        if ($configured !== '') {
            if (is_file($configured)) {
                return $configured;
            }

            $candidates[] = storage_path($configured);
            $candidates[] = base_path($configured);

            // Tolerate accidental "storage/app/..." values in .env
            if (str_starts_with($configured, 'storage/')) {
                $candidates[] = base_path($configured);
            }
        }

        $candidates[] = storage_path('app/firebase/clinicmanagementsystem-6a7f2-firebase-adminsdk-fbsvc-522de7e4e3.json');

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Firebase credentials file not found. Set FIREBASE_CREDENTIALS to a valid Admin SDK JSON under storage/app/firebase/.'
        );
    }

    public static function projectId(): string
    {
        $json = json_decode((string) file_get_contents(self::path()), true);

        return (string) ($json['project_id'] ?? '');
    }
}
