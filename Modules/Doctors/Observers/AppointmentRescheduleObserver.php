<?php

namespace Modules\Doctors\Observers;

use Modules\Doctors\Models\AppointmentReschedule;
use Modules\Notifications\Services\NotificationService;

class AppointmentRescheduleObserver
{


    /**
     * عند إنشاء طلب تأجيل
     */
    public function created(
        AppointmentReschedule $reschedule
    ): void
    {


        $appointment =
            $reschedule->appointment;



        if(!$appointment)
        {
            return;
        }



        app(NotificationService::class)
        ->sendToUser(

            $appointment->user,


            'Appointment Change Request 🔄',


            'The doctor requested to change your appointment time. Please accept or reject.',


            [

                'appointment_id'=>
                    $appointment->id,


                'reschedule_id'=>
                    $reschedule->id,


                'old_date'=>
                    $reschedule->old_date,


                'old_time'=>
                    $reschedule->old_time,


                'new_date'=>
                    $reschedule->new_date,


                'new_time'=>
                    $reschedule->new_time

            ]

        );


    }



    /**
     * عند موافقة أو رفض المريض
     */
    public function updated(
        AppointmentReschedule $reschedule
    ): void
    {


        if(!$reschedule->wasChanged('status'))
        {
            return;
        }



        $appointment =
            $reschedule->appointment;



        if(!$appointment)
        {
            return;
        }



        /*
        |--------------------------------------------------------------------------
        | المريض وافق
        |--------------------------------------------------------------------------
        */


        if(
            $reschedule->status === 'accepted'
        )
        {

            app(NotificationService::class)
            ->sendToUser(

                $appointment->doctor->user,


                'Appointment Reschedule Accepted ✅',


                'The patient accepted the new appointment time.',


                [

                    'appointment_id'=>
                        $appointment->id,


                    'date'=>
                        $reschedule->new_date,


                    'time'=>
                        $reschedule->new_time

                ]

            );


        }




        /*
        |--------------------------------------------------------------------------
        | المريض رفض
        |--------------------------------------------------------------------------
        */


        if(
            $reschedule->status === 'rejected'
        )
        {

            app(NotificationService::class)
            ->sendToUser(

                $appointment->doctor->user,


                'Appointment Reschedule Rejected ❌',


                'The patient rejected the new appointment time.',


                [

                    'appointment_id'=>
                        $appointment->id,


                    'reschedule_id'=>
                        $reschedule->id

                ]

            );

        }


    }


}