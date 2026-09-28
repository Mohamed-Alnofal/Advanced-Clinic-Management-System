<?php
namespace Modules\Appointments\Services;

use App\Events\AppointmentApproved;
// use App\Events\AppointmentBooked;
// use App\Events\AppointmentCancelledByDoctor;
// use App\Events\AppointmentCancelledByPatient;
use App\Events\AppointmentRejected;
// use App\Models\Appointment;
// use App\Models\Relatives;
// use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Appointments\Events\AppointmentBooked;
use Modules\Appointments\Events\AppointmentCancelledByPatient;
use Modules\Appointments\Models\Appointment;
use Modules\Core\Services\FirebaseService;
use Modules\Doctors\Models\DoctorHoliday;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Users\Models\Relatives;
use Modules\Users\Models\User;
use Illuminate\Validation\ValidationException;
// use App\Models\DoctorSchedule;
use Modules\Consultation\Models\Consultation;
use Modules\Appointments\Services\AppointmentEscrowService;
// PaymentService is resolved lazily inside payForAppointment only —
// so booking never boots Stripe / payment adapters.
class AppointmentService
{
   public function __construct(
    private AppointmentEscrowService $escrow,
    private AppointmentSlotResolver $slotResolver,
) {}

public function book(array $data, $user)
{
    return DB::transaction(function () use ($data, $user) {

        // Release abandoned Stripe/wallet holds so retries are not blocked.
        Appointment::query()
            ->where('status', 'awaiting_payment')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update([
                'status' => 'cancelled',
                'cancel_reason' => 'Payment timed out',
            ]);

        $schedule = DoctorSchedule::lockForUpdate()
            ->find($data['schedule_id']);

        if (!$schedule) {
            return [
                'status' => false,
                'message' => 'Schedule not found'
            ];
        }

        $appointmentDate = $this->slotResolver->resolveDateFromBooking($schedule, $data);

        if (! $this->slotResolver->dateMatchesScheduleDay($schedule, $appointmentDate)) {
            throw ValidationException::withMessages([
                'appointment_date' => 'Selected date does not match this slot weekday.',
            ]);
        }

        $appointmentType = $data['appointment_type'] ?? ($schedule->consultation_type ?? 'clinic');
        $clinicId = $schedule->clinic_id ? (int) $schedule->clinic_id : null;
        if (DoctorHoliday::coversDate(
            (int) $schedule->doctor_id,
            $appointmentDate,
            $clinicId,
            is_string($appointmentType) ? strtolower($appointmentType) : 'clinic'
        )) {
            throw ValidationException::withMessages([
                'appointment_date' => 'This doctor is on holiday for the selected date.',
            ]);
        }

        // Retry path: same user already started Stripe/wallet but payment did not finish.
        $reusable = Appointment::query()
            ->where('user_id', $user->id)
            ->where('schedule_id', $schedule->id)
            ->whereDate('appointment_date', $appointmentDate)
            ->where('status', 'awaiting_payment')
            ->lockForUpdate()
            ->first();

        if ($reusable) {
            return $this->finalizeBookedAppointment(
                $reusable,
                $schedule,
                $user,
                $data,
                $appointmentDate,
                $appointmentType,
                reused: true,
            );
        }

        if ($this->slotResolver->isBookedForDate((int) $schedule->id, $appointmentDate, (int) $user->id)) {
            return [
                'status' => false,
                'message' => 'هذا الموعد محجوز مسبقاً لهذا التاريخ. اختر وقتاً آخر.',
                'code' => 'slot_already_booked',
            ];
        }

        $scheduleType = $schedule->consultation_type ?? 'clinic';
        if ($scheduleType !== $appointmentType) {
            throw ValidationException::withMessages([
                'appointment_type' => 'This slot is for '.$scheduleType.' visits, not '.$appointmentType.'.',
            ]);
        }

        $bookingFor = 'self';

        if (
            isset($data['booking_for']) &&
            $data['booking_for'] === 'relative'
        ) {

            Relatives::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'age' => $data['age'],
                'gender' => $data['gender'],
            ]);

            $bookingFor = 'relative';
        }

        $newSchedule = DoctorSchedule::find($data['schedule_id']);

        $sameTimeScheduleIds = DoctorSchedule::where('start_time', $newSchedule->start_time)
            ->where('day', $newSchedule->day)
            ->pluck('id');

        $exists = Appointment::where('user_id', $user->id)
            ->whereIn('schedule_id', $sameTimeScheduleIds)
            ->whereNotIn('status', ['rejected', 'cancelled', 'awaiting_payment'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'schedule' => 'لديك بالفعل موعد في نفس الوقت عند طبيب آخر'
            ]);
        }
    $resolvedClinicId = $data['clinic_id'] ?? $schedule->clinic_id;
    if ($appointmentType === 'clinic') {
        if (! $resolvedClinicId) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Clinic is required for in-person appointments.',
            ]);
        }
        // Slot must belong to the clinic being booked.
        if ($schedule->clinic_id && (int) $schedule->clinic_id !== (int) $resolvedClinicId) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Selected clinic does not match this time slot.',
            ]);
        }
    } else {
        // Video appointments still need a clinic_id FK; fall back to doctor's clinic.
        if (! $resolvedClinicId) {
            $resolvedClinicId = \Modules\Clinics\Models\Clinic::where('doctor_id', $data['doctor_id'])->value('id');
        }
        if (! $resolvedClinicId) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Doctor must have at least one clinic before accepting video bookings.',
            ]);
        }
    }

        $fee = (float) ($schedule->price ?? 0);
        [$initialStatus, $initialPaymentMethod] = $this->resolveInitialPaymentState($fee, $data);

        $appointment = Appointment::create([
            'doctor_id' => $data['doctor_id'],
            'clinic_id' => $resolvedClinicId,
            'user_id' => $user->id,
            'schedule_id' => $data['schedule_id'],
            'appointment_date' => $appointmentDate,
            'booking_for' => $bookingFor,
            'status' => $initialStatus,
            'appointment_type' => $appointmentType,
            'consultation_fee' => $fee > 0 ? $fee : null,
            'payment_method' => $initialPaymentMethod,
        ]);

        return $this->finalizeBookedAppointment(
            $appointment,
            $schedule,
            $user,
            $data,
            $appointmentDate,
            $appointmentType,
            reused: false,
        );
    });
}

/**
 * @return array{0:string,1:string}
 */
private function resolveInitialPaymentState(float $fee, array $data): array
{
    $wantsEscrow = filter_var($data['use_wallet_escrow'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $paymentMethod = strtolower((string) ($data['payment_method'] ?? ''));

    if ($fee <= 0) {
        return ['pending', 'none'];
    }
    if ($wantsEscrow || $paymentMethod === 'wallet') {
        return ['awaiting_payment', 'wallet'];
    }
    if (in_array($paymentMethod, ['pay_later', 'pay_at_clinic'], true)) {
        return ['pending', 'pay_at_clinic'];
    }
    if ($paymentMethod === 'stripe') {
        return ['awaiting_payment', 'stripe'];
    }

    return ['awaiting_payment', 'none'];
}

private function finalizeBookedAppointment(
    Appointment $appointment,
    DoctorSchedule $schedule,
    $user,
    array $data,
    string $appointmentDate,
    string $appointmentType,
    bool $reused,
): array {
    $fee = (float) ($schedule->price ?? $appointment->consultation_fee ?? 0);
    $wantsEscrow = filter_var($data['use_wallet_escrow'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $paymentMethod = strtolower((string) ($data['payment_method'] ?? ''));

    if ($reused) {
        [$status, $method] = $this->resolveInitialPaymentState($fee, $data);
        $appointment->update([
            'status' => $status,
            'payment_method' => $method,
            'consultation_fee' => $fee > 0 ? $fee : $appointment->consultation_fee,
        ]);
        $appointment->refresh();
    }

    $requiresVideoEscrow = $appointmentType === 'video' && $fee > 0;

    if ($requiresVideoEscrow || $wantsEscrow || $paymentMethod === 'wallet') {
        if ($requiresVideoEscrow) {
            $data['use_wallet_escrow'] = true;
        }
        $this->escrow->maybeCreateHold($appointment, $user, $schedule, $data);
        $appointment->refresh();
        if ($appointment->payment_hold_id) {
            $appointment->update(['status' => 'pending', 'payment_method' => 'wallet_escrow']);
        }
    } elseif (in_array($paymentMethod, ['pay_later', 'pay_at_clinic'], true)) {
        $appointment->update([
            'status' => 'pending',
            'payment_method' => 'pay_at_clinic',
        ]);
    }

    $appointment = $appointment->fresh();

    return [
        'status' => true,
        'message' => $reused
            ? 'تم استكمال حجز الموعد السابق بانتظار الدفع.'
            : 'Appointment booked successfully',
        'data' => [
            'appointment_id' => $appointment->id,
            'schedule_id' => $schedule->id,
            'doctor_id' => $appointment->doctor_id,
            'clinic_id' => $appointment->clinic_id,
            'appointment_date' => $appointmentDate,
            'status' => $appointment->status,
            'booking_for' => $appointment->booking_for,
            'appointment_type' => $appointmentType,
            'payment_method' => $appointment->payment_method,
            'amount' => $fee,
            'reused' => $reused,
        ],
    ];
}


        public function payForAppointment(array $data, $user)
{
    $appointment = Appointment::where('id', $data['appointment_id'])
        ->where('user_id', $user->id)
        ->first();

    if (! $appointment) {
        return [
            'status' => false,
            'message' => 'Appointment not found',
        ];
    }

    if ($appointment->status !== 'awaiting_payment') {
        return [
            'status' => false,
            'message' => 'This appointment does not require payment.',
        ];
    }

    $schedule = DoctorSchedule::find($appointment->schedule_id);

    if (! $schedule) {
        return [
            'status' => false,
            'message' => 'Schedule not found.',
        ];
    }

    $amount = (float) ($schedule->price ?? $appointment->consultation_fee ?? 0);
    if ($amount <= 0) {
        return [
            'status' => false,
            'message' => 'This appointment has no payable fee.',
        ];
    }

    // Stripe is an external HTTP call — keep it outside the DB transaction.
    if (($data['payment_method'] ?? '') === 'stripe') {
        try {
            $paymentResult = app(\Modules\Payment\Services\PaymentService::class)->pay(
                new \Modules\Payment\DTOs\PaymentDTO(
                    amount: $amount,
                    paymentMethod: 'stripe',
                    userId: $user->id,
                    referenceType: 'appointment',
                    referenceId: $appointment->id,
                    idempotencyKey: 'appointment_'.$appointment->id,
                )
            );
        } catch (\Throwable $e) {
            report($e);

            return [
                'status' => false,
                'message' => 'Stripe checkout failed: '.$e->getMessage(),
            ];
        }

        if (($paymentResult['success'] ?? false) !== true) {
            return [
                'status' => false,
                'message' => $paymentResult['message'] ?? 'Payment could not be created.',
            ];
        }

        $appointment->update([
            'consultation_fee' => $amount,
            'payment_method' => 'stripe',
        ]);

        return [
            'status' => true,
            'message' => 'Stripe checkout created successfully.',
            'data' => [
                'appointment_id' => $appointment->id,
                'payment_status' => 'awaiting_payment',
                'payment_method' => 'stripe',
                'stripe_session_id' => $paymentResult['stripe_session_id'] ?? null,
                'checkout_url' => $paymentResult['checkout_url'] ?? null,
            ],
        ];
    }

    return DB::transaction(function () use ($data, $user, $appointment, $schedule, $amount) {
        $appointment = Appointment::where('id', $appointment->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (! $appointment || $appointment->status !== 'awaiting_payment') {
            return [
                'status' => false,
                'message' => 'This appointment does not require payment.',
            ];
        }

        $paymentResult = app(
            \Modules\Payment\Services\PaymentService::class
        )->pay(
            new \Modules\Payment\DTOs\PaymentDTO(
                amount: $amount,
                paymentMethod: $data['payment_method'],
                userId: $user->id,
                referenceType: 'appointment',
                referenceId: $appointment->id,
                idempotencyKey: 'appointment_'.$appointment->id,
            )
        );

        if (($paymentResult['success'] ?? false) !== true) {
            return [
                'status' => false,
                'message' => $paymentResult['message']
                    ?? 'Payment could not be created.',
            ];
        }

        $appointment->update([
            'status' => 'pending',
            'consultation_fee' => $amount,
            'payment_method' => $data['payment_method'],
            'payment_hold_id' => $paymentResult['wallet_hold_id'] ?? $appointment->payment_hold_id,
        ]);

        return [
            'status' => true,
            'message' => 'Payment successful.',
            'data' => [
                'appointment_id' => $appointment->id,
                'status' => 'pending',
                'payment_status' => 'paid',
                'payment_method' => $data['payment_method'],
            ],
        ];
    });
}
    public function reschedule(array $data, $user)
    {
        return DB::transaction(function () use ($data, $user) {

            // 🔍 جلب الموعد
            $appointment = Appointment::where('id', $data['appointment_id'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$appointment) {
                return [
                    'status' => false,
                    'message' => 'Appointment not found'
                ];
            }

            // 🔥 جلب الموعد الجديد
            $newSchedule = DoctorSchedule::lockForUpdate()
                ->find($data['new_schedule_id']);

            if (!$newSchedule) {
                return [
                    'status' => false,
                    'message' => 'New schedule not found'
                ];
            }

            // ❌ لو محجوز
            if ($newSchedule->is_booked) {
                return [
                    'status' => false,
                    'message' => 'Selected slot is already booked'
                ];
            }

            $currentType = $appointment->appointment_type ?? 'clinic';
            $newType = $newSchedule->consultation_type ?? 'clinic';
            if ($currentType !== $newType) {
                throw ValidationException::withMessages([
                    'new_schedule_id' => 'Cannot reschedule a '.$currentType.' appointment to a '.$newType.' slot.',
                ]);
            }

        $newSchedule = DoctorSchedule::find($data['new_schedule_id']);

        $sameTimeScheduleIds = DoctorSchedule::where('start_time', $newSchedule->start_time)
            ->pluck('id');

        $exists = Appointment::where('user_id', $user->id)
            ->whereIn('schedule_id', $sameTimeScheduleIds)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'schedule' => 'لديك بالفعل موعد في نفس الوقت عند طبيب آخر'
            ]);
        }
                
            // ❌ منع التعديل قبل 24 ساعة (اختياري مهم)
            // $appointmentTime = Carbon::parse($newSchedule->start_datetime);
            // if ($appointmentTime->diffInHours(now()) < 24) {
            //     return [
            //         'status' => false,
            //         'message' => 'Cannot reschedule within 24 hours'
            //     ];
            // }

            // 🟢 تحرير الموعد القديم
            $oldSchedule = DoctorSchedule::find($appointment->schedule_id);
            if ($oldSchedule) {
                $oldSchedule->update(['is_booked' => false]);
            }

            // 🟢 تحديث الموعد
            $appointment->update([
                'schedule_id' => $newSchedule->id,
                'status' => 'pending'
            ]);

            // 🟢 حجز الموعد الجديد
            $newSchedule->update([
                'is_booked' => true
            ]);

            // event(new AppointmentRescheduled($appointment, $user));
  // ✅ إذا كان الموعد فيديو، تحديث الاستشارة
            if ($appointment->appointment_type === 'video') {
                $consultation = Consultation::updateOrCreate(
                    ['appointment_id' => $appointment->id],
                    [
                        'room_name' => Consultation::generateRoomName(),
                        'status' => 'scheduled',
                    ]
                );
            }
            return [
                    'status' => true,
                    'message' => 'Appointment rescheduled successfully',

                    'data' => [
                        'appointment_id' => $appointment->id,

                        'schedule_id' => $newSchedule->id,

                        'doctor_id' => $appointment->doctor_id,
                        'clinic_id' => $appointment->clinic_id,

                        'booking_for' => $appointment->booking_for,

                        'status' => $appointment->status,
                    'appointment_type' => $appointment->appointment_type,
                        // ✔ معلومات الوقت من schedule الجديد
                        'day' => $newSchedule->day,
                        'start_time' => $newSchedule->start_time,
                        'end_time' => $newSchedule->end_time,
                    ]
                ];
        });
    }
public function cancelAppointment($schedule_id, $user)
{
    return DB::transaction(function () use ($schedule_id, $user) {

        $schedule = DoctorSchedule::lockForUpdate()
            ->findOrFail($schedule_id);

        $appointment = Appointment::where('schedule_id', $schedule_id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'accepted', 'awaiting_payment'])
            ->firstOrFail();

        $appointmentTime = Carbon::parse($schedule->start_time);
        $now = Carbon::now();

        // الفرق بالساعات (موجب إذا الموعد في المستقبل)
        $diffInHours = $now->diffInHours($appointmentTime, false);

        // // منع الإلغاء إذا أقل من 24 ساعة
        // if ($diffInHours < 24 && $diffInHours >= 0) {
        //     return [
        //         'status' => false,
        //         'message' => 'You cannot cancel the appointment within 24 hours of the scheduled time.',
        //     ];
        // }

        $appointment->update([
            'status' => 'cancelled',
            'cancel_reason' => 'Cancelled by patient'
        ]);

        $this->escrow->releaseIfHeld($appointment->fresh());

        $schedule->update([
            'is_booked' => false
        ]);

 if ($appointment->appointment_type === 'video') {
                Consultation::where('appointment_id', $appointment->id)
                    ->update(['status' => 'cancelled']);
            }
        return [
            'status' => true,
            'message' => 'Appointment cancelled successfully',
            'data' => [
                'schedule_id' => $schedule->id,
                'available_again' => true
            ]
        ];
    });
}
    // public function getUserAppointments(User $user)
    // {
    //     return $user->appointments()
    //         ->with(['doctor.user'])
    //         ->latest()
    //         ->get()
    //         ->map(function ($appointment) {
    //             return [
    //                 'id' => $appointment->id,
    //                 'date' => $appointment->date,
    //                 'time' => $appointment->time,
    //                 'status' => $appointment->status,

    //                 'doctor' => [
    //                     'id' => $appointment->doctor->id,
    //                     'name' => $appointment->doctor->user->name,
    //                 ],
    //             ];
    //         });
    // }
    public function deleteAppointment($appointment_id, $user)
    {
        return DB::transaction(function () use ($appointment_id, $user) {

            // جلب الموعد للمستخدم الحالي
            $appointment = Appointment::where('id', $appointment_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$appointment) {
                return [
                    'status' => false,
                    'message' => 'Appointment not found'
                ];
            }

            // حفظ schedule_id قبل الحذف
            $scheduleId = $appointment->schedule_id;

            // جعل الموعد متاحًا من جديد
            if ($scheduleId) {
                DoctorSchedule::where('id', $scheduleId)
                    ->update([
                        'is_booked' => 0
                    ]);
            }
            if ($appointment->appointment_type === 'video') {
                            Consultation::where('appointment_id', $appointment->id)->delete();
                        }

            $this->escrow->releaseIfHeld($appointment);

            // حذف الموعد
            $appointment->delete();

            return [
                'status' => true,
                'message' => 'Appointment deleted successfully',
                'data' => [
                    'appointment_id' => $appointment_id,
                    'schedule_id' => $scheduleId,
                    'available_again' => true
                ]
            ];
        });
    }
public function getUserAppointments(User $user)
{
    $dayMap = [
        'sunday' => Carbon::SUNDAY,
        'monday' => Carbon::MONDAY,
        'tuesday' => Carbon::TUESDAY,
        'wednesday' => Carbon::WEDNESDAY,
        'thursday' => Carbon::THURSDAY,
        'friday' => Carbon::FRIDAY,
        'saturday' => Carbon::SATURDAY,
    ];

    return $user->appointments()
        ->with(['doctor.user', 'schedule', 'consultation'])
        ->latest()
        ->get()
        ->map(function ($appointment) use ($dayMap) {

            $schedule = $appointment->schedule;

            // لو ما في schedule
            if (!$schedule) {
                return null;
            }

            // حساب أقرب تاريخ لهذا اليوم (day)
            $targetDay = $dayMap[$schedule->day] ?? null;

            $date = $targetDay
                ? Carbon::now()->next($targetDay)->toDateString()
                : null;

            return [
                'id' => $appointment->id,

                'schedule_id' => $appointment->schedule_id,
                // التاريخ المحسوب من day
                
                'day' => $schedule->day,
                
                'time' => $schedule->start_time,

                'date' => $date,
                
                'status' => $appointment->status,
                'appointment_type' => $appointment->appointment_type,

                'doctor' => [
                    'id' => $appointment->doctor->id,
                    'name' => $appointment->doctor->user->name,
                ],
            'consultation' => $appointment->consultation ? [  // ✅ إضافة
                        'id' => $appointment->consultation->id,
                        'room_name' => $appointment->consultation->room_name,
                        'status' => $appointment->consultation->status,
                    ] : null,
                ];
        })
        ->filter(); // حذف null إذا schedule غير موجود
}


    public function doctorAction($appointment_id, $doctor, $status, $reason = null)
    {
        return DB::transaction(function () use ($appointment_id, $doctor, $status, $reason) {

            $appointment = Appointment::where('id', $appointment_id)
                ->where('doctor_id', $doctor->id)
                ->first();

            if (! $appointment) {
                return ['status' => false, 'message' => 'Appointment not found'];
            }

            $firebase = new FirebaseService();

            switch ($status) {

                case 'accepted':

                    if ($appointment->status !== 'pending') {
                        return ['status' => false, 'message' => 'Invalid state'];
                    }

                    $appointment->update(['status' => 'accepted']);

                    // Escrow capture happens on completed, not accept.

                    // event(new AppointmentApproved($appointment));

                    // Keep consultation scheduled until someone actually joins the call.
                    if ($appointment->appointment_type === 'video') {
                        Consultation::where('appointment_id', $appointment->id)
                            ->whereNotIn('status', ['completed', 'cancelled'])
                            ->update(['status' => 'scheduled']);
                    }
                    return ['status' => true, 'message' => 'Accepted'];

                    case 'rejected':

                    if ($appointment->status !== 'pending') {
                        return ['status' => false, 'message' => 'Invalid state'];
                    }

                    $appointment->update([
                        'status' => 'rejected',
                        'reject_reason' => $reason
                    ]);

                    $this->escrow->releaseIfHeld($appointment->fresh());

                    if ($appointment->schedule_id) {
                        DoctorSchedule::where('id', $appointment->schedule_id)
                            ->update(['is_booked' => false]);
                    }

                    // event(new AppointmentRejected($appointment));

                     if ($appointment->appointment_type === 'video') {
                    Consultation::where('appointment_id', $appointment->id)
                        ->update(['status' => 'cancelled']);
                }
                    return ['status' => true, 'message' => 'Rejected'];

                    case 'completed':

                    if ($appointment->status !== 'accepted') {
                        return ['status' => false, 'message' => 'Invalid state'];
                    }

                    $appointment->update(['status' => 'completed']);

                    $this->escrow->captureIfHeld($appointment->fresh());

                    if ($appointment->appointment_type === 'video') {
                    Consultation::where('appointment_id', $appointment->id)
                        ->update(['status' => 'completed']);
                }
                    return ['status' => true, 'message' => 'Completed'];

                    case 'cancelled':

                        if (!in_array($appointment->status, ['accepted', 'pending'])) {
                            return ['status' => false, 'message' => 'Invalid state'];
                        }

                        $appointment->update([
                            'status' => 'cancelled',
                            'cancel_reason' => $reason
                        ]);

                        $this->escrow->releaseIfHeld($appointment->fresh());

                        if ($appointment->schedule_id) {
                            DoctorSchedule::where('id', $appointment->schedule_id)
                                ->update(['is_booked' => false]);
                        }

                        // event(new AppointmentCancelledByDoctor($appointment));
                        if ($appointment->appointment_type === 'video') {
                    Consultation::where('appointment_id', $appointment->id)
                        ->update(['status' => 'cancelled']);
                }

                        return ['status' => true, 'message' => 'Cancelled by doctor'];
            }

            return ['status' => false, 'message' => 'Invalid action'];
        });
    }
}