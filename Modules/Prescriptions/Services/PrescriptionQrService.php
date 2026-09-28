<?php

namespace Modules\Prescriptions\Services;

class PrescriptionQrService
{
    /**
     * Generate secure QR payload.
     *
     * Example:
     * RX|25|signature
     */
    public function generatePayload(int $prescriptionId): string
    {
        $signature = hash_hmac(
            'sha256',
            "prescription:{$prescriptionId}",
            config('app.key')
        );

        return "RX|{$prescriptionId}|{$signature}";
    }

    /**
     * Verify QR payload and return prescription ID.
     */
    public function verifyPayload(string $payload): ?int
    {
        $parts = explode('|', $payload);

        if (count($parts) !== 3) {
            return null;
        }

        [$prefix, $prescriptionId, $signature] = $parts;

        if ($prefix !== 'RX') {
            return null;
        }

        if (!ctype_digit($prescriptionId)) {
            return null;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            "prescription:{$prescriptionId}",
            config('app.key')
        );

        if (!hash_equals(
            $expectedSignature,
            $signature
        )) {
            return null;
        }

        return (int) $prescriptionId;
    }
}