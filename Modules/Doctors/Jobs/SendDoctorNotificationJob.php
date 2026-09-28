<?php
namespace Modules\Doctors\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Modules\Doctors\Events\DoctorApproved;
use Modules\Doctors\Events\DoctorRegistered;
use Modules\Doctors\Events\DoctorRejected;
use Modules\Notifications\Services\FirebaseNotificationService;
use Modules\Users\Models\User;

class SendDoctorNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $event) {}

    public function handle(FirebaseNotificationService $firebase)
    {
        try {

            $doctor = $this->event->doctor ?? null;
            if (!$doctor) return;

            $user = $doctor->user ?? null;

            // =========================
            // 1. REGISTERED → ADMIN
            // =========================
            if ($this->event instanceof DoctorRegistered) {

                $admins = User::where('role', 'admin')->get();

                foreach ($admins as $admin) {
                    $this->send($firebase, $admin,
                        'طلب طبيب جديد',
                        "طبيب جديد: {$doctor->name}",
                        'doctor_registered',
                        ['doctor_id' => $doctor->id]
                    );
                }
            }

            // =========================
            // 2. APPROVED
            // =========================
            if ($this->event instanceof DoctorApproved) {

                if (!$user) return;

                $this->send($firebase, $user,
                    'تم قبول حسابك',
                    'تم قبول حسابك كطبيب',
                    'doctor_approved',
                    ['doctor_id' => $doctor->id]
                );
            }

            // =========================
            // 3. REJECTED
            // =========================
            if ($this->event instanceof DoctorRejected) {

                if (!$user) return;

                $this->send($firebase, $user,
                    'تم رفض حسابك',
                    "سبب الرفض: {$this->event->reason}",
                    'doctor_rejected',
                    [
                        'doctor_id' => $doctor->id,
                        'reason' => $this->event->reason
                    ]
                );
            }

        } catch (\Throwable $e) {
            logger()->error('Doctor Notification Job Error: '.$e->getMessage());
        }
    }

    private function send($firebase, $user, $title, $body, $type, $data)
    {
        if ($user?->fcm_token) {
            $firebase->send(
                $user->fcm_token,
                $title,
                $body,
                array_merge($data, ['type' => $type])
            );
        }

        if ($user) {
            $user->notifications()->create([
                'id' => Str::uuid(),
                'type' => $type,
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    ...$data
                ],
            ]);
        }
    }
}