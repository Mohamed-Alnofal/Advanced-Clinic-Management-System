<?php

use Illuminate\Support\Facades\Route;
use Modules\Doctors\Http\Controllers\AppointmentRescheduleController;
use Modules\Doctors\Http\Controllers\DoctorDashboardController;
use Modules\Doctors\Http\Controllers\DoctorDayActionController;
use Modules\Doctors\Http\Controllers\DoctorReviewController;
use Modules\Doctors\Http\Controllers\DoctorScheduleController;
use Modules\Doctors\Http\Controllers\DoctorHolidayController;
use Modules\Doctors\Http\Controllers\DoctorMonthlyScheduleController;

Route::middleware('auth:api')->group(function () {

    Route::post('/rate', [DoctorReviewController::class, 'rate']);
});

Route::middleware('auth:api')->group(function () {

    Route::get('/doctor/dashboard', DoctorDashboardController::class);

    //Route::get('/doctor/schedule/{id}', [DoctorScheduleController::class, 'show']);

    Route::post('/doctor/schedule', [DoctorScheduleController::class, 'store']);

    Route::get('/doctor/schedule-slot/{id}', [DoctorScheduleController::class, 'showSlot']);

    Route::get('/doctor/schedule/{id}', [DoctorScheduleController::class, 'show']);

    Route::get('/doctor/my-schedule', [DoctorScheduleController::class, 'mySchedule']);

    Route::delete('/doctor/schedules', [DoctorScheduleController::class, 'destroySchedules']);

});



Route::prefix('doctors')
    ->middleware(['auth:api'])
    ->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Holidays
        |--------------------------------------------------------------------------
        */


        Route::get(
            'holidays',
            [DoctorHolidayController::class,'index']
        );


        Route::post(
            'holidays',
            [DoctorHolidayController::class,'store']
        );


        Route::post(
            'holidays/{holiday}',
            [DoctorHolidayController::class,'update']
        );


        Route::delete(
            'holidays/{holiday}',
            [DoctorHolidayController::class,'destroy']
        );



        /*
        |--------------------------------------------------------------------------
        | Monthly Schedule
        |--------------------------------------------------------------------------
        */


        Route::post(
            'monthly-schedule/generate',
            [
                DoctorMonthlyScheduleController::class,
                'generate'
            ]
        );


        Route::get(
            'monthly-schedule',
            [
                DoctorMonthlyScheduleController::class,
                'index'
            ]
        );


        Route::get(
            '/{doctor}/calendar',
            [
                DoctorMonthlyScheduleController::class,
                'patientCalendar'
            ]
        );


        Route::get(
            '/{doctor}/available-slots',
            [
                DoctorMonthlyScheduleController::class,
                'availableSlots'
            ]
        );

        Route::get(
    '/{doctor}/monthly-available-slots',
    [
        DoctorMonthlyScheduleController::class,
        'monthlyAvailableSlots'
    ]
);
});

Route::prefix('appointments')
->middleware('auth:api')
->group(function(){



    Route::post(
    '{appointment}/reschedule',
    [
    AppointmentRescheduleController::class,
    'store'
    ]
    );



    Route::post(
    'reschedules/{reschedule}/accept',
    [
    AppointmentRescheduleController::class,
    'accept'
    ]
    );



    Route::post(
    'reschedules/{reschedule}/reject',
    [
    AppointmentRescheduleController::class,
    'reject'
    ]
    );



    });


Route::prefix('doctors')
->middleware('auth:api')
->group(function(){



    /*
    |--------------------------------------------------------------------------
    | Cancel all appointments of a day
    |--------------------------------------------------------------------------
    */


    Route::post(
        'schedule/day-cancel',
        [
            DoctorDayActionController::class,
            'cancelDay'
        ]
    );



    /*
    |--------------------------------------------------------------------------
    | Delay all appointments of a day
    |--------------------------------------------------------------------------
    */


    Route::post(
        'schedule/day-delay',
        [
            DoctorDayActionController::class,
            'delayDay'
        ]
    );



});