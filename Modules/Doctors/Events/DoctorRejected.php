<?php
namespace Modules\Doctors\Events;

class DoctorRejected
{
    public function __construct(
        public $doctor,
        public string $reason
    ) {}
}