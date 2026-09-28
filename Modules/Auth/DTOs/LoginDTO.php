<?php

namespace Modules\Auth\DTOs;

class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password
    ) {}
}