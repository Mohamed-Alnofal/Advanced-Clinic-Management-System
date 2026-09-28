<?php
namespace Modules\Doctors\Events;

class DoctorApproved
{
    public function __construct(public $doctor) {}
}