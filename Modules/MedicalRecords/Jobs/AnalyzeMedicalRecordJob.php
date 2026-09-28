<?php

namespace Modules\MedicalRecords\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\MedicalRecords\Models\MedicalRecord;
use Modules\MedicalRecords\Services\MedicalAnalysisService;

class AnalyzeMedicalRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Soft limit — text PDF parsing should finish far sooner. */
    public $timeout = 120;

    public $tries = 2;

    public $backoff = [5, 15];

    protected int $recordId;

    public function __construct(MedicalRecord $record)
    {
        $this->recordId = $record->id;
        $this->onQueue('default');
    }

    public function handle(MedicalAnalysisService $service): void
    {
        $record = MedicalRecord::with('patient')->find($this->recordId);
        if (! $record) {
            return;
        }

        $record = MedicalRecord::with('patient')->findOrFail($this->recordId);

        Log::info('START MEDICAL ANALYSIS', ['record' => $record->id]);

        $record->update(['status' => 'processing', 'error_message' => null]);

        try {
            app(\Modules\MedicalRecords\Services\MedicalReferenceRangeResolver::class)->forgetCache();
            $result = $service->analyze($record);

            $record->update([
                'report' => $result['report'],
                'extracted_data' => $result['extracted_data'],
                'status' => 'ready',
                'analyzed_at' => now(),
                'error_message' => null,
            ]);

            Log::info('MEDICAL RECORD READY', [
                'record' => $record->id,
                'tests' => count($result['report']['items'] ?? []),
                'duration_ms' => $result['extracted_data']['duration_ms'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('MEDICAL ANALYSIS ERROR', [
                'record' => $record->id,
                'message' => $e->getMessage(),
            ]);

            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
