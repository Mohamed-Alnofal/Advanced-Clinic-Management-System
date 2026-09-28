<?php

namespace Modules\Doctors\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Doctors\Services\DoctorReviewService;
use Modules\Core\Http\Controllers\Controller;
class DoctorController extends Controller
{
    protected $service;

    public function __construct(DoctorReviewService $service)
    {
        $this->service = $service;
    }



}