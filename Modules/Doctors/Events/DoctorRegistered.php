<?php
namespace Modules\Doctors\Events;

class DoctorRegistered
{
    public function __construct(public $doctor) {}
}