<?php

namespace Modules\Prescriptions\Policies;

use Modules\Prescriptions\Models\Prescriptions;
use Modules\Users\Models\User;

class PrescriptionPolicy
{
    public function exportPdf(
        User $user,
        Prescriptions $prescription
    ): bool {

        $prescription->loadMissing([
            'visit.doctor',
            'visit.patientFile.patient.patientable',
        ]);


        $visit =
            $prescription->visit;


        if (!$visit) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Doctor
        |--------------------------------------------------------------------------
        */

        $doctor =
            $visit->doctor;


        if (
            $doctor &&
            (int) $doctor->user_id ===
            (int) $user->id
        ) {

            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Patient
        |--------------------------------------------------------------------------
        */

        $patient =
            $visit
                ->patientFile
                ?->patient;


        if (!$patient) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Patient belongs to current user
        |--------------------------------------------------------------------------
        */

        return
            $patient->patientable_type ===
                User::class

            &&

            (int) $patient->patientable_id ===
                (int) $user->id;
    }
}