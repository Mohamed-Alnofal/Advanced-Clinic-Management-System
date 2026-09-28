<?php

namespace Modules\Doctors\States\InterfaceFactory;

interface DoctorStateInterface
{
    public function canLogin(): bool;

    public function canUploadDocuments(): bool;

    public function message(): string;
}