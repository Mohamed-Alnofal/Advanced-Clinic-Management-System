<?php

namespace Modules\Auth\DTOs;

class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $role,

        public ?string $phone = null,
        public ?string $date_of_birth=null,
        public ?string $gender = null,

        public ?int $specialization_id = null,
        public ?string $bio = null,
        public ?int $experience_years = null,

        public ?string $image = null,

        public array $medical_certificates = [],

        public ?string $pharmacy_name = null,
        public ?string $license_number = null,

        public ?string $clinic_address = null,
       
        public ?int $governorate_id = null,
        public ?string $clinic_name = null,
       
    ) {}
}
