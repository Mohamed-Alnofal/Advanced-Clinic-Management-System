<?php

namespace Modules\MedicalRecords\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalRecords\Jobs\AnalyzeMedicalRecordJob;
use Modules\MedicalRecords\Models\MedicalRecord;
use Modules\MedicalRecords\Support\MedicalRecordReportPresenter;

class MedicalRecordsController extends Controller
{
    public function __construct(
        private readonly MedicalRecordReportPresenter $reportPresenter,
    ) {
        $this->middleware('auth:api');
    }

    /**
     * POST /api/v1/medical-records
     * multipart: file (pdf|txt), title?, description?
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,txt|max:10240',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Send Authorization: Bearer <token>.',
            ], 401);
        }

        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $fileName = Str::uuid().'.'.$extension;
        $path = $file->storeAs("medical_records/{$user->id}", $fileName, 'public');

        $record = MedicalRecord::create([
            'patient_id' => $user->id,
            'uploaded_by' => $user->id,
            'title' => $validated['title'] ?? $file->getClientOriginalName(),
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        try {
            // Run after the HTTP response so upload stays fast and does NOT
            // depend on a separate queue:work window (XAMPP-friendly).
            AnalyzeMedicalRecordJob::dispatchAfterResponse($record);
        } catch (\Throwable $e) {
            Log::error('Failed to queue medical analysis', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
            $record->update([
                'status' => 'failed',
                'error_message' => 'Could not start analysis job.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'File uploaded. Analysis started.',
            'data' => [
                'id' => $record->id,
                'title' => $record->title,
                'status' => $record->status,
                'created_at' => optional($record->created_at)?->toIso8601String(),
            ],
        ], 202);
    }

    /** GET /api/v1/medical-records */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));

        $records = MedicalRecord::query()
            ->where('patient_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->getCollection()->map(fn (MedicalRecord $record) => [
                'id' => $record->id,
                'title' => $record->title,
                'file_name' => $record->file_name,
                'status' => $record->status,
                'is_ready' => $record->isReady(),
                'has_report' => ! is_null($record->report),
                'created_at' => optional($record->created_at)?->toIso8601String(),
                'analyzed_at' => optional($record->analyzed_at)?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
            ],
        ]);
    }

    /** GET /api/v1/medical-records/{id} */
    public function show($id)
    {
        $record = $this->ownedRecord($id);

        $data = [
            'id' => $record->id,
            'title' => $record->title,
            'description' => $record->description,
            'file_name' => $record->file_name,
            'mime_type' => $record->mime_type,
            'status' => $record->status,
            'is_ready' => $record->isReady(),
            'created_at' => optional($record->created_at)?->toIso8601String(),
            'analyzed_at' => optional($record->analyzed_at)?->toIso8601String(),
            'file_url' => $record->file_path ? Storage::disk('public')->url($record->file_path) : null,
        ];

        if ($record->isReady() && $record->report) {
            $record->loadMissing('patient');
            $patient = $record->patient;
            $gender = $patient?->gender ?? null;
            $age = $patient?->age !== null ? (int) $patient->age : null;

            $data['report'] = $this->reportPresenter->present(
                $record->report,
                $gender,
                $age,
            );
        }

        if ($record->status === 'failed') {
            $data['error_message'] = $record->error_message;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /** GET /api/v1/medical-records/{id}/status */
    public function status($id)
    {
        $record = $this->ownedRecord($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $record->id,
                'status' => $record->status,
                'is_ready' => $record->isReady(),
                'is_processing' => $record->isProcessing(),
                'error_message' => $record->error_message,
                'analyzed_at' => optional($record->analyzed_at)?->toIso8601String(),
            ],
        ]);
    }

    /** DELETE /api/v1/medical-records/{id} */
    public function destroy($id)
    {
        $record = $this->ownedRecord($id);

        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
            Storage::disk('public')->delete($record->file_path);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Medical record deleted.',
        ]);
    }

    /** GET /api/v1/medical-records/{id}/download */
    public function download($id)
    {
        $record = $this->ownedRecord($id);

        if (! $record->file_path || ! Storage::disk('public')->exists($record->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found on storage.',
            ], 404);
        }

        return Storage::disk('public')->download($record->file_path, $record->file_name);
    }

    private function ownedRecord($id): MedicalRecord
    {
        return MedicalRecord::query()
            ->where('id', $id)
            ->where('patient_id', Auth::id())
            ->firstOrFail();
    }
}
