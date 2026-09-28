<?php

namespace Modules\Laboratory\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Doctors\Models\Doctor;
use Modules\Laboratory\Models\Laboratory;
use Modules\Laboratory\Models\LaboratoryOrder;
use Modules\Laboratory\Models\LaboratoryResult;
use Modules\MedicalVisits\Models\MedicalVisits;
use Symfony\Component\HttpFoundation\StreamedResponse;
class LaboratoryOrderService
{
    /**
     * إنشاء طلب تحاليل من زيارة طبية
     */
    public function createOrder(
        Doctor $doctor,
        int $medicalVisitId,
        int $laboratoryId,
        array $tests,
        ?string $doctorNotes = null
    ): LaboratoryOrder {

        return DB::transaction(function () use (
            $doctor,
            $medicalVisitId,
            $laboratoryId,
            $tests,
            $doctorNotes
        ) {

            /*
             * التأكد أن الزيارة تخص الطبيب الحالي
             */
            $visit = MedicalVisits::with('patientFile')
                ->where('id', $medicalVisitId)
                ->where('doctor_id', $doctor->id)
                ->firstOrFail();

            /*
             * الحصول على الملف الطبي
             */
            $patientFile = $visit->patientFile;

            /*
             * إنشاء الطلب
             */
            $order = LaboratoryOrder::create([
                'doctor_id' => $doctor->id,

                'patient_id' => $patientFile->patient_id,

                'patient_file_id' => $patientFile->id,

                'medical_visit_id' => $visit->id,

                'laboratory_id' => $laboratoryId,

                'doctor_notes' => $doctorNotes,

                'status' => 'pending',
            ]);

            /*
             * إضافة التحاليل
             */
            foreach ($tests as $test) {

                $order->items()->create([
                    'test_name' => $test,
                    'status' => 'pending',
                ]);
            }

            return $order->load([
                'doctor',
                'patient',
                'patientFile',
                'medicalVisit',
                'laboratory',
                'items',
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | READ - ALL
    |--------------------------------------------------------------------------
    */

    public function getDoctorOrders(
        Doctor $doctor,
        ?string $status = null,
        ?int $medicalVisitId = null
    ) {

        $query = LaboratoryOrder::with([
            'patient',
            'patientFile',
            'medicalVisit',
            'laboratory',
            'items',
            'result',
        ])
        ->where('doctor_id', $doctor->id)
        ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($medicalVisitId) {
            $query->where('medical_visit_id', $medicalVisitId);
        }

        return $query->paginate(15);
    }

    /**
     * Active verified laboratories for doctor order picker.
     */
    public function listAvailableLaboratories()
    {
        return Laboratory::query()
            ->where('is_active', true)
            ->where('is_verified', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'license_number',
                'phone',
                'address',
                'image',
            ]);
    }

    /**
     * Lab orders belonging to a patient record.
     */
    public function getPatientOrders(
        int $patientId,
        ?string $status = null
    ) {
        $query = LaboratoryOrder::with([
            'doctor.user',
            'laboratory',
            'items',
            'results',
            'medicalVisit',
        ])
            ->where('patient_id', $patientId)
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate(15);
    }

    public function getPatientOrder(
        LaboratoryOrder $order,
        int $patientId
    ): LaboratoryOrder {
        if ((int) $order->patient_id !== $patientId) {
            abort(403, 'Unauthorized access to this laboratory order.');
        }

        return $order->load([
            'doctor.user',
            'laboratory',
            'items',
            'results',
            'medicalVisit',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | READ - ONE
    |--------------------------------------------------------------------------
    */

    public function getOrder(
        Doctor $doctor,
        int $orderId
    ): LaboratoryOrder {

        return LaboratoryOrder::with([
            'doctor',
            'patient',
            'patientFile',
            'medicalVisit',
            'laboratory',
            'items',
            'result',
        ])
        ->where('id', $orderId)
        ->where('doctor_id', $doctor->id)
        ->firstOrFail();
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function updateOrder(
        Doctor $doctor,
        int $orderId,
        int $laboratoryId,
        array $tests,
        ?string $doctorNotes = null
    ): LaboratoryOrder {

        return DB::transaction(function () use (
            $doctor,
            $orderId,
            $laboratoryId,
            $tests,
            $doctorNotes
        ) {

            /*
             * الطلب يجب أن يكون تابعًا للطبيب
             */
            $order = LaboratoryOrder::where('id', $orderId)
                ->where('doctor_id', $doctor->id)
                ->firstOrFail();

            /*
             * لا يمكن تعديل الطلب بعد أن يبدأ المخبر العمل عليه
             */
            if ($order->status !== 'pending') {
                abort(
                    422,
                    'Only pending laboratory orders can be updated.'
                );
            }

            /*
             * تحديث بيانات الطلب
             */
            $order->update([
                'laboratory_id' => $laboratoryId,
                'doctor_notes' => $doctorNotes,
            ]);

            /*
             * حذف التحاليل القديمة
             */
            $order->items()->delete();

            /*
             * إضافة التحاليل الجديدة
             */
            foreach ($tests as $test) {

                $order->items()->create([
                    'test_name' => $test,
                    'status' => 'pending',
                ]);
            }

            return $order->fresh([
                'doctor',
                'patient',
                'patientFile',
                'medicalVisit',
                'laboratory',
                'items',
            ]);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function deleteOrder(
        Doctor $doctor,
        int $orderId
    ): bool {

        return DB::transaction(function () use (
            $doctor,
            $orderId
        ) {

            $order = LaboratoryOrder::where('id', $orderId)
                ->where('doctor_id', $doctor->id)
                ->firstOrFail();

            /*
             * لا يمكن حذف الطلب بعد بدء العمل
             */
            if ($order->status !== 'pending') {
                abort(
                    422,
                    'Only pending laboratory orders can be deleted.'
                );
            }

            /*
             * حذف العناصر
             *
             * إذا كان foreign key عندك cascadeOnDelete
             * فهي ستحذف تلقائيًا، لكن هذا واضح وصريح.
             */
            $order->items()->delete();

            $order->delete();

            return true;
        });
    }

    /**
     * جلب طلب معين للطبيب
     */
    public function getDoctorOrder(
        LaboratoryOrder $order,
        Doctor $doctor
    ): LaboratoryOrder {

        if ($order->doctor_id !== $doctor->id) {
            abort(403, 'Unauthorized access to this laboratory order.');
        }

        return $order->load([
            'doctor',
            'patient',
            'patientFile',
            'medicalVisit',
            'laboratory',
            'items',
            'result',
        ]);
    }

    /**
     * جلب طلب معين للمخبر
     */
    public function getLaboratoryOrder(
        LaboratoryOrder $order,
        int $laboratoryId
    ): LaboratoryOrder {

        if ($order->laboratory_id !== $laboratoryId) {
            abort(403, 'Unauthorized access to this laboratory order.');
        }

        return $order->load([
            'doctor',
            'patient',
            'patientFile',
            'medicalVisit',
            'laboratory',
            'items',
            'result',
        ]);
    }

    // /**
    //  * جلب طلبات الطبيب
    //  */
    // public function getDoctorOrders(
    //     Doctor $doctor,
    //     ?string $status = null
    // ) {

    //     $query = LaboratoryOrder::query()
    //         ->where('doctor_id', $doctor->id)
    //         ->with([
    //             'laboratory',
    //             'patient',
    //             'medicalVisit',
    //             'items',
    //             'result',
    //         ])
    //         ->latest();

    //     if ($status) {
    //         $query->where('status', $status);
    //     }

    //     return $query->paginate(15);
    // }

    /**
     * جلب طلبات المخبر
     */
    public function getLaboratoryOrders(
        int $laboratoryId,
        ?string $status = null
    ) {

        $query = LaboratoryOrder::query()
            ->where('laboratory_id', $laboratoryId)
            ->with([
                'doctor',
                'patient',
                'medicalVisit',
                'items',
                'result',
            ])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate(15);
    }

    /**
     * بدء العمل على الطلب
     *
     * pending → in_progress
     */
    public function startOrder(
        LaboratoryOrder $order,
        int $laboratoryId
    ): LaboratoryOrder {

        if ($order->laboratory_id !== $laboratoryId) {
            abort(403, 'Unauthorized access to this laboratory order.');
        }

        if ($order->status !== 'pending') {
            abort(
                422,
                'Only pending orders can be started.'
            );
        }

        $order->update([
            'status' => 'in_progress',
        ]);

        /*
         * تحديث جميع التحاليل
         */
        $order->items()->update([
            'status' => 'in_progress',
        ]);

        return $order->fresh([
            'doctor',
            'patient',
            'laboratory',
            'items',
        ]);
    }

    /**
     * رفع نتيجة التحاليل
     */
   /**
 * رفع نتيجة التحاليل
 */

// public function uploadResult(
//     LaboratoryOrder $order,
//     int $laboratoryId,
//     UploadedFile $file
// ): LaboratoryResult {

//     if ($order->laboratory_id !== $laboratoryId) {
//         abort(403, 'Unauthorized access to this laboratory order.');
//     }

//     return DB::transaction(function () use (
//         $order,
//         $file
//     ) {

//         /*
//          * حفظ الملف الجديد
//          */
//         $path = $file->store(
//             'laboratory/results',
//             'public'
//         );

//         /*
//          * إنشاء نتيجة جديدة
//          */
//         $result = LaboratoryResult::create([
//             'laboratory_order_id' => $order->id,
//             'result_file' => $path,
//             'original_file_name' => $file->getClientOriginalName(),
//         ]);

//         /*
//          * جميع التحاليل أصبحت مكتملة
//          */
//         $order->items()->update([
//             'status' => 'completed',
//         ]);

//         /*
//          * الطلب أصبح مكتملًا
//          */
//         $order->update([
//             'status' => 'completed',
//         ]);

//         return $result->load([
//             'laboratoryOrder.doctor',
//             'laboratoryOrder.patient',
//             'laboratoryOrder.laboratory',
//         ]);
//     });
// }

public function uploadResult(
    LaboratoryOrder $order,
    int $laboratoryId,
    UploadedFile $file
): LaboratoryResult {

    if ($order->laboratory_id !== $laboratoryId) {
        abort(
            403,
            'Unauthorized access to this laboratory order.'
        );
    }

    return DB::transaction(function () use (
        $order,
        $file
    ) {

        $path = $file->store(
            'laboratory/results',
            'public'
        );

        $result = LaboratoryResult::create([
            'laboratory_order_id' => $order->id,
            'result_file' => $path,
            'original_file_name' => $file->getClientOriginalName(),
        ]);

        $order->items()->update([
            'status' => 'completed',
        ]);

        $order->update([
            'status' => 'completed',
        ]);

        return $result->load([
            'laboratoryOrder.doctor',
            'laboratoryOrder.patient',
            'laboratoryOrder.laboratory',
        ]);
    });
}
    /**
     * الحصول على نتيجة الطلب
     */
    public function getResult(
        LaboratoryOrder $order
    ): ?LaboratoryResult {

        return $order->load('result')->result;
    }



    //     public function download(
    //     LaboratoryResult $result
    // ): StreamedResponse {

    //     $disk = Storage::disk('public');

    //     if (!$disk->exists($result->result_file)) {
    //         abort(404, 'Laboratory result file not found.');
    //     }

    //     return $disk->download(
    //         $result->result_file,
    //         $result->original_file_name
    //     );
    // }
    public function download(
    LaboratoryResult $result
): StreamedResponse {

    $disk = Storage::disk('public');

    if (!$disk->exists($result->result_file)) {
        abort(
            404,
            'Laboratory result file not found.'
        );
    }

    return $disk->download(
        $result->result_file,
        $result->original_file_name
    );
}

public function getResults(
    LaboratoryOrder $order
) {
    return $order->results()->get();
}
}