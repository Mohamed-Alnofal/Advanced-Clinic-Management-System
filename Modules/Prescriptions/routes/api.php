<?php

use Illuminate\Support\Facades\Route;
use Modules\Prescriptions\Http\Controllers\PrescriptionDispensingController;
use Modules\Prescriptions\Http\Controllers\PrescriptionsController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('prescriptions')->group(function () {

        Route::get('/', [
            PrescriptionsController::class,
            'index'
        ]);

        Route::post('/', [
            PrescriptionsController::class,
            'store'
        ]);

        Route::get('{id}', [
            PrescriptionsController::class,
            'show'
        ]);

        Route::post('{id}', [
            PrescriptionsController::class,
            'update'
        ]);

        Route::delete('{id}', [
            PrescriptionsController::class,
            'destroy'
        ]);

        Route::get(
        '/{patient}/patients',
        [
            PrescriptionsController::class,
            'patientPrescriptions'
        ]
     );
    });


    Route::get(
        '/prescriptions/{id}/pdf',
        [PrescriptionsController::class, 'pdf']
    );

});

// Route::prefix('pharmacy')
//     ->middleware('auth:api')
//     ->group(function () {

//         /*
//         |--------------------------------------------------------------------------
//         | Scan prescription QR
//         |--------------------------------------------------------------------------
//         */

//         Route::post(
//             'prescriptions/scan',
//             [
//                 PrescriptionDispensingController::class,
//                 'scan'
//             ]
//         );


//         /*
//         |--------------------------------------------------------------------------
//         | Dispense prescription
//         |--------------------------------------------------------------------------
//         */

//         Route::post(
//             'prescriptions/dispense',
//             [
//                 PrescriptionDispensingController::class,
//                 'dispense'
//             ]
//         );


//         /*
//         |--------------------------------------------------------------------------
//         | Download archived prescription
//         |--------------------------------------------------------------------------
//         */

//         Route::get(
//             'prescription-dispensings/{dispensingId}/archive',
//             [
//                 PrescriptionDispensingController::class,
//                 'downloadArchive'
//             ]
//         );

//         Route::get(
//     'prescription-dispensings',
//     [
//         PrescriptionDispensingController::class,
//         'index'
//     ]
// );
// Route::get(
//     'prescription-dispensings/archives',
//     [
//         PrescriptionDispensingController::class,
//         'archivedFiles'
//     ]
// );

//     });
Route::prefix('pharmacy')
    ->middleware([
        'auth:api',
        'role:pharmacist'
    ])
    ->middleware(['auth:api', 'role:pharmacist'])
    ->group(function () {

        /*
        |----------------------------------------------------------------------
        | Scan prescription QR
        |----------------------------------------------------------------------
        */

        Route::post(
            'prescriptions/scan',
            [
                PrescriptionDispensingController::class,
                'scan'
            ]
        );


        /*
        |----------------------------------------------------------------------
        | Dispense prescription
        |----------------------------------------------------------------------
        */

        Route::post(
            'prescriptions/dispense',
            [
                PrescriptionDispensingController::class,
                'dispense'
            ]
        );


        /*
        |----------------------------------------------------------------------
        | Download archived prescription
        |----------------------------------------------------------------------
        */

        Route::get(
            'prescription-dispensings/{dispensingId}/archive',
            [
                PrescriptionDispensingController::class,
                'downloadArchive'
            ]
        );


        /*
        |----------------------------------------------------------------------
        | All dispensing records
        |----------------------------------------------------------------------
        */

        Route::get(
            'prescription-dispensings',
            [
                PrescriptionDispensingController::class,
                'index'
            ]
        );


        /*
        |----------------------------------------------------------------------
        | Archived files
        |----------------------------------------------------------------------
        */

        Route::get(
            'prescription-dispensings/archives',
            [
                PrescriptionDispensingController::class,
                'archivedFiles'
            ]
        );

    });