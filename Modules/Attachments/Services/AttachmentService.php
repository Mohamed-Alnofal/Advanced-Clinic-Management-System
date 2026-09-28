<?php

namespace Modules\Attachments\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Attachments\Models\Attachments;
use Modules\MedicalVisits\Models\MedicalVisits;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientChartAccessService;
use App\Support\PublicMedia;

class AttachmentService
{
    public function __construct(
        private PatientChartAccessService $chartAccess,
    ) {}

    public function store($request)
    {
        $user = auth()->user();
        $visit = MedicalVisits::findOrFail($request->medical_visit_id);
        $file = PatientFile::findOrFail($visit->patient_file_id);
        $this->chartAccess->assertCanAccessFile($file, $user, true);

        $path = PublicMedia::store($request->file('file'), 'medical-attachments');

        $attachment = Attachments::create([
            'medical_visit_id' => $request->medical_visit_id,
            'file' => $path,
            'type' => $request->type,
        ]);

        return [
            'id' => $attachment->id,
            'medical_visit_id' => $attachment->medical_visit_id,
            'type' => $attachment->type,
            'file_url' => PublicMedia::url($path),
        ];
    }

    public function download($id)
    {
        $attachment = Attachments::findOrFail($id);
        $this->assertCanAccessAttachment($attachment);

        $disk = Storage::disk('public');
        if ($disk->exists($attachment->file)) {
            return $disk->download($attachment->file);
        }

        return Storage::download($attachment->file);
    }

    public function destroy($id)
    {
        $attachment = Attachments::findOrFail($id);
        $this->assertCanAccessAttachment($attachment, true);

        Storage::disk('public')->delete($attachment->file);
        Storage::delete($attachment->file);

        $attachment->delete();
    }

    private function assertCanAccessAttachment(Attachments $attachment, bool $write = false): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        $visit = MedicalVisits::findOrFail($attachment->medical_visit_id);
        $file = PatientFile::findOrFail($visit->patient_file_id);
        $this->chartAccess->assertCanAccessFile($file, $user, $write);
    }
}
