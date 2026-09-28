<?php

namespace Modules\Doctors\States;

use Modules\Doctors\States\InterfaceFactory\DoctorStateInterface;

class ActiveState implements DoctorStateInterface
{
    public function canLogin(): bool
    {
        return false;
    }



    public function canUploadDocuments(): bool
    {
        return true;
    }

    public function message(): string
    {
        return 'Waiting for admin approval';
    }
}