<?php

namespace Modules\Doctors\States;

use Modules\Doctors\States\InterfaceFactory\DoctorStateInterface;

class SuspendedState implements DoctorStateInterface
{
    public function canLogin(): bool
    {
        return false;
    }


    public function canUploadDocuments(): bool
    {
        return false;
    }

    public function message(): string
    {
        return 'Your account has been suspended';
    }
}