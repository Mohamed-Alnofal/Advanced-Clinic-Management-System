<?php

namespace Modules\Pharmacist\States;

use Modules\Pharmacist\States\InterfaceFactory\PharmacyStateInterface;

class RejectedState implements PharmacyStateInterface
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