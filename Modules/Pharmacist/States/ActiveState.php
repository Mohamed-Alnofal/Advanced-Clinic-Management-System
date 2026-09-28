<?php

namespace Modules\Pharmacist\States;

use Modules\Pharmacist\States\InterfaceFactory\PharmacyStateInterface;

class ActiveState implements PharmacyStateInterface
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