<?php

namespace Modules\Pharmacist\States\InterfaceFactory;

use Modules\Pharmacist\States\ActiveState;
use Modules\Pharmacist\States\AcceptedState;
use Modules\Pharmacist\States\RejectedState;
use Modules\Pharmacist\States\SuspendedState;

class PharmacyStateFactory
{
    public static function make(string $status)
    {
        return match ($status) {

            'active' => new ActiveState(),

            'accepted' => new AcceptedState(),

            'rejected' => new RejectedState(),

            'suspended' => new SuspendedState(),

            default => new SuspendedState(),
        };
    }
}