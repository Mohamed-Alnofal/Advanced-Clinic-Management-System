<?php

namespace Modules\Doctors\States\InterfaceFactory;

use Modules\Doctors\States\AcceptedState;
use Modules\Doctors\States\PendingState;
use Modules\Doctors\States\RejectedState;
use Modules\Doctors\States\SuspendedState;

class DoctorStateFactory
{
    public static function make(?string $status)
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'accepted', 'approved' => new AcceptedState(),
            'rejected' => new RejectedState(),
            'suspended' => new SuspendedState(),
            // Legacy "active" was used as "awaiting approval" in older code.
            'pending', 'active', '' => new PendingState(),
            default => new PendingState(),
        };
    }
}
