<?php

namespace Modules\Doctors\States;

use Modules\Doctors\States\InterfaceFactory\DoctorStateInterface;

class RejectedState implements DoctorStateInterface
{
    public function canLogin(): bool
    {
        return true;
    }



    public function canUploadDocuments(): bool
    {
        return true;
    }

    public function message(): string
    {
        return 'Your account was rejected';
    }
}