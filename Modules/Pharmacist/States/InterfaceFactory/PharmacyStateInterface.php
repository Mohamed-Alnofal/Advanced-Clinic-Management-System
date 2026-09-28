<?php

namespace Modules\Pharmacist\States\InterfaceFactory;

interface PharmacyStateInterface
{
    public function canLogin(): bool;

    public function canUploadDocuments(): bool;

    public function message(): string;
}