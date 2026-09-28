<?php

namespace Modules\Patients\Observers;

use App\Jobs\SendNotificationJob;

class PatientAccessRequestObserver
{
    /**
     * 🟢 إنشاء طلب جديد
     */
    public function created($request): void
    {
            \Log::info('STEP 1: Observer Fired', [
        'request_id' => $request->id
    ]);
        $this->notifyPatientNewAccessRequest($request);
    }

    /**
     * 🟡 تحديث الحالة (approve / reject)
     */
    public function updated($request): void
    {
        if ($request->isDirty('status')) {

            if ($request->status === 'approved') {
                $this->notifyRequestApproved($request);
            }

            if ($request->status === 'rejected') {
                $this->notifyRequestRejected($request);
            }
        }
    }

    /**
     * 🟢 طلب جديد → للمريض
     */
// private function notifyPatientNewAccessRequest($request): void
// {
//     $patient = $request->patient;

//     $user = $patient->patientable; // 👈 هذا هو الصحيح

//     if (!$user) {
//         \Log::error('NO PATIENTABLE FOUND', [
//             'patient_id' => $patient->id
//         ]);
//         return;
//     }

//     SendNotificationJob::dispatch(
//         $user,
//         'medical_access_request',
//         'Medical Access Request 🏥',
//         'A doctor is requesting access to your medical file',
//         [
//             'request_id' => $request->id,
//             'doctor_id' => $request->doctor_id
//         ]
//     );
// }
private function notifyPatientNewAccessRequest($request): void
{
    try {
        $patient = $request->patient;
        $user = $patient?->patientable;
        if (! $user) {
            return;
        }

        $doctorName = $request->doctor?->user?->name ?? 'Doctor';

        SendNotificationJob::dispatch(
            $user,
            'medical_access_request',
            'Access request for your medical record',
            'Dr. '.$doctorName.' requests access to a visit in your chart.',
            [
                'request_id' => $request->id,
                'doctor_id' => $request->doctor_id,
                'doctor_name' => $doctorName,
                'patient_file_id' => $request->patient_file_id,
                'medical_visit_id' => $request->medical_visit_id,
                'action' => 'open_access_review',
            ]
        );
    } catch (\Throwable $e) {
        \Log::warning('Access request notify failed', ['error' => $e->getMessage()]);
    }
}
    /**
     * 🟢 موافقة المريض → للطبيب
     */
    private function notifyRequestApproved($request): void
    {
        try {
            $doctorUser = $request->doctor?->user;
            if (! $doctorUser) {
                return;
            }

            SendNotificationJob::dispatch(
                $doctorUser,
                'access_request',
                'Access approved',
                'The patient has approved your access request.',
                [
                    'request_id' => $request->id,
                    'patient_id' => $request->patient_id,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Access approved notify failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyRequestRejected($request): void
    {
        try {
            $doctorUser = $request->doctor?->user;
            if (! $doctorUser) {
                return;
            }

            SendNotificationJob::dispatch(
                $doctorUser,
                'access_request',
                'Access rejected',
                'The patient has rejected your access request.',
                [
                    'request_id' => $request->id,
                    'patient_id' => $request->patient_id,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Access rejected notify failed', ['error' => $e->getMessage()]);
        }
    }
}