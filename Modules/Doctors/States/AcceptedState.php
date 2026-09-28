<?php

namespace Modules\Doctors\States;

use Modules\Doctors\States\InterfaceFactory\DoctorStateInterface;

// use Modules\Doctors\States\InterfaceFactory\DoctorStateInterface;

// use Modules\Doctor\States\DoctorState;

class AcceptedState implements DoctorStateInterface
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