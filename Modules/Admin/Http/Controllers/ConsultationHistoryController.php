<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\Consultation\Models\Consultation;

class ConsultationHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Consultation::with([
            'appointment.doctor.user',
            'videoAudits.user',
        ]);

        if ($request->filled('appointment_id')) {
            $query->where('appointment_id', $request->input('appointment_id'));
        }

        if ($request->filled('doctor_id')) {
            $query->whereHas('appointment.doctor', function ($doctorQuery) use ($request) {
                $doctorQuery->where('id', $request->input('doctor_id'));
            });
        }

        if ($request->filled('doctor_name')) {
            $doctorName = trim((string) $request->input('doctor_name'));

            $query->whereHas('appointment.doctor.user', function ($userQuery) use ($doctorName) {
                $userQuery->where('name', 'like', '%' . $doctorName . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $consultations = $query
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'status' => true,
            'data' => $consultations->getCollection()->map(function ($consultation) {
                return [
                    'consultation' => [
                        'id' => $consultation->id,
                        'appointment_id' => $consultation->appointment_id,
                        'room_name' => $consultation->room_name,
                        'status' => $consultation->status,
                        'started_at' => $consultation->started_at,
                        'ended_at' => $consultation->ended_at,
                        'doctor_name' => $consultation->appointment?->doctor?->user?->name,
                        'duration_seconds' => $consultation->started_at && $consultation->ended_at
                            ? $consultation->started_at->diffInSeconds($consultation->ended_at)
                            : null,
                    ],
                    'history' => $consultation->videoAudits->map(function ($audit) {
                        return [
                            'id' => $audit->id,
                            'event' => $audit->event,
                            'user_id' => $audit->user_id,
                            'user_name' => $audit->user?->name,
                            'user_role' => $audit->user_role,
                            'ip_address' => $audit->ip_address,
                            'created_at' => $audit->created_at,
                            'details' => $audit->details,
                        ];
                    })->values(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $consultations->currentPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
                'last_page' => $consultations->lastPage(),
            ],
        ]);
    }

    public function show($appointmentId)
    {
        $consultation = Consultation::with([
            'appointment.doctor',
            'videoAudits.user',
        ])
            ->where('appointment_id', $appointmentId)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => [
                'consultation' => [
                    'id' => $consultation->id,
                    'appointment_id' => $consultation->appointment_id,
                    'room_name' => $consultation->room_name,
                    'status' => $consultation->status,
                    'started_at' => $consultation->started_at,
                    'ended_at' => $consultation->ended_at,
                    'duration_seconds' => $consultation->started_at && $consultation->ended_at
                        ? $consultation->started_at->diffInSeconds($consultation->ended_at)
                        : null,
                ],
                'history' => $consultation->videoAudits->map(function ($audit) {
                    return [
                        'id' => $audit->id,
                        'event' => $audit->event,
                        'user_id' => $audit->user_id,
                        'user_name' => $audit->user?->name,
                        'user_role' => $audit->user_role,
                        'ip_address' => $audit->ip_address,
                        'created_at' => $audit->created_at,
                        'details' => $audit->details,
                    ];
                })->values(),
            ],
        ]);
    }
}