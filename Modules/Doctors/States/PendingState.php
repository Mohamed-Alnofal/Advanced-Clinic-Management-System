<?php

namespace Modules\Doctors\States;

use Modules\Doctors\States\InterfaceFactory\DoctorStateInterface;

class PendingState implements DoctorStateInterface
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
        return 'Your application is pending admin review';
    }
}
