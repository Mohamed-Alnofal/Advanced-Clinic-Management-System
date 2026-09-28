<?php

namespace Modules\Pharmacist\States;

use Modules\Pharmacist\States\InterfaceFactory\PharmacyStateInterface;

// use Modules\Pharmacist\States\InterfaceFactory\DoctorStateInterface;

// use Modules\Doctor\States\DoctorState;

class AcceptedState implements PharmacyStateInterface
{
    public function canLogin(): bool
    {
        return true;
    }

    public function canUploadDocuments(): bool
    {
        return false;
    }

    public function message(): string
    {
        return 'Welcome Doctor';
    }
}