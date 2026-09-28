<?php

namespace Modules\Doctors\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Doctors\Database\factories\DoctorDayActionFactory;

class DoctorDayAction extends Model
{


protected $fillable=[

'doctor_id',
'date',
'action',
'delay_minutes',
'reason'

];



}