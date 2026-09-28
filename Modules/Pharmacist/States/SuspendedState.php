<?php

namespace Modules\Pharmacist\States;

use Modules\Pharmacist\States\InterfaceFactory\PharmacyStateInterface;

class SuspendedState implements PharmacyStateInterface
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