<?php

namespace Modules\Prescriptions\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use Modules\Prescriptions\Models\Prescriptions;
use Modules\Prescriptions\Models\PrescriptionDispensing;

class PrescriptionDispensingService
{
    public function __construct(
        protected PrescriptionQrService $qrService,
        protected PrescriptionExportService $exportService
    ) {
    }

    /**
     * Verify QR and return prescription information.
     */
    public function verifyQr(
        string $payload
    ): array {

        $prescriptionId =
            $this->qrService->verifyPayload(
                $payload
            );

        if (!$prescriptionId) {

            throw ValidationException::withMessages([
                'qr' => 'Invalid prescription QR code.'
            ]);
        }

        $prescription =
            Prescriptions::with([
                'items',

                'visit.doctor.user',

                'visit.doctor.specialization',

                'visit.patientFile.patient.patientable',
            ])
            ->find($prescriptionId);

        if (!$prescription) {

            throw ValidationException::withMessages([
                'qr' => 'Prescription not found.'
            ]);
        }

        $dispensedCount =
            $prescription
                ->dispensings()
                ->count();

        $alreadyDispensed =
            $prescription->dispensing_type === 'one_time'
            &&
            $dispensedCount > 0;

        return [
            'valid' => !$alreadyDispensed,

            'prescription_id' =>
                $prescription->id,

            'dispensing_type' =>
                $prescription->dispensing_type,

            'already_dispensed' =>
                $alreadyDispensed,

            'dispensed_count' =>
                $dispensedCount,

            'doctor' => [
                'id' =>
                    $prescription->visit?->doctor?->id,

                'name' =>
                    $prescription->visit?->doctor?->user?->name,

                'specialization' =>
                    $prescription->visit?->doctor?->specialization?->name,
            ],

            'patient' => [
                'name' =>
                    $prescription->visit?->patientFile?->patient?->patientable?->name,
            ],

            'items' =>
                $prescription->items
                    ->map(function ($item) {

                        return [
                            'medicine_name' =>
                                $item->medicine_name,

                            'dose' =>
                                $item->dose,

                            'frequency' =>
                                $item->frequency,

                            'duration' =>
                                $item->duration,

                            'instructions' =>
                                $item->instructions,
                        ];

                    })
                    ->values(),
        ];
    }


    /**
     * Dispense prescription and archive PDF.
     */
    public function dispense(
        string $payload,
        int $pharmacyId,
        int $pharmacistId
    ): array {

        return DB::transaction(function () use (
            $payload,
            $pharmacyId,
            $pharmacistId
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1. Verify QR
            |--------------------------------------------------------------------------
            */

            $prescriptionId =
                $this->qrService->verifyPayload(
                    $payload
                );

            if (!$prescriptionId) {

                throw ValidationException::withMessages([
                    'qr' => 'Invalid prescription QR code.'
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | 2. Lock prescription
            |--------------------------------------------------------------------------
            */

            $prescription =
                Prescriptions::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $prescriptionId
                    );


            /*
            |--------------------------------------------------------------------------
            | 3. Check one-time prescription
            |--------------------------------------------------------------------------
            */

            if (
                $prescription->dispensing_type ===
                'one_time'
            ) {

                $alreadyDispensed =
                    $prescription
                        ->dispensings()
                        ->exists();

                if ($alreadyDispensed) {

                    throw ValidationException::withMessages([
                        'prescription' =>
                            'This prescription has already been dispensed.'
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | 4. Generate exact PDF snapshot
            |--------------------------------------------------------------------------
            */

            $pdf =
                $this->exportService
                    ->generatePdfContent(
                        $prescription->id,
                        false
                    );


            $pdfContent =
                $pdf['content'];


            /*
            |--------------------------------------------------------------------------
            | 5. Generate hash
            |--------------------------------------------------------------------------
            */

            $pdfHash =
                hash(
                    'sha256',
                    $pdfContent
                );


            /*
            |--------------------------------------------------------------------------
            | 6. Create dispensing record first
            |--------------------------------------------------------------------------
            */

            $dispensing =
                PrescriptionDispensing::create([

                    'prescription_id' =>
                        $prescription->id,

                    'pharmacy_id' =>
                        $pharmacyId,

                    'pharmacist_id' =>
                        $pharmacistId,

                    'dispensed_at' =>
                        now(),

                    'status' =>
                        'dispensed',
                ]);


            /*
            |--------------------------------------------------------------------------
            | 7. Save archived private PDF
            |--------------------------------------------------------------------------
            */

            $path =
                'prescription-archives/' .
                'pharmacy-' .
                $pharmacyId .
                '/' .
                'prescription-' .
                $prescription->id .
                '/' .
                'dispensing-' .
                $dispensing->id .
                '.pdf';


            Storage::disk('local')
                ->put(
                    $path,
                    $pdfContent
                );


            /*
            |--------------------------------------------------------------------------
            | 8. Update archive information
            |--------------------------------------------------------------------------
            */

            $dispensing->update([

                'archived_pdf_path' =>
                    $path,

                'pdf_hash' =>
                    $pdfHash,

            ]);


            return [
                'message' =>
                    'Prescription dispensed successfully.',

                'dispensing_id' =>
                    $dispensing->id,

                'prescription_id' =>
                    $prescription->id,

                'dispensed_at' =>
                    $dispensing->dispensed_at,

                'archive' => [
                    'path' => $path,
                    'sha256' => $pdfHash,
                ],
            ];
        });
    }


    /**
     * Download an archived prescription.
     */
    public function downloadArchive(
        int $dispensingId,
        int $pharmacyId
    ) {

        $dispensing =
            PrescriptionDispensing::query()
                ->where('id', $dispensingId)
                ->where(
                    'pharmacy_id',
                    $pharmacyId
                )
                ->firstOrFail();


        if (
            !$dispensing->archived_pdf_path
        ) {

            throw ValidationException::withMessages([
                'archive' =>
                    'Archived prescription file not found.'
            ]);
        }


        if (
            !Storage::disk('local')
                ->exists(
                    $dispensing->archived_pdf_path
                )
        ) {

            throw ValidationException::withMessages([
                'archive' =>
                    'Archived prescription file does not exist.'
            ]);
        }


        return Storage::disk('local')
            ->download(
                $dispensing->archived_pdf_path,
                'prescription-' .
                $dispensing->prescription_id .
                '-dispensing-' .
                $dispensing->id .
                '.pdf'
            );
    }

        public function index(
        int $pharmacyId,
        int $perPage = 15
    ) {
        return PrescriptionDispensing::query()
            ->where('pharmacy_id', $pharmacyId)
            ->with([
                'prescription.items',
                'prescription.visit.doctor.user',
                'pharmacist',
            ])
            ->latest('dispensed_at')
            ->paginate($perPage);
    }


public function archivedFiles(
    int $pharmacyId,
    int $perPage = 15
) {
    $dispensings = PrescriptionDispensing::query()
        ->where('pharmacy_id', $pharmacyId)
        ->whereNotNull('archived_pdf_path')
        ->latest('dispensed_at')
        ->paginate($perPage);

    $dispensings->getCollection()->transform(function ($dispensing) {

        $fileExists = Storage::disk('local')->exists(
            $dispensing->archived_pdf_path
        );

        return [
            'dispensing_id' => $dispensing->id,

            'prescription_id' =>
                $dispensing->prescription_id,

            'pharmacy_id' =>
                $dispensing->pharmacy_id,

            'pharmacist_id' =>
                $dispensing->pharmacist_id,

            'dispensed_at' =>
                $dispensing->dispensed_at,

            'status' =>
                $dispensing->status,

            'file_exists' =>
                $fileExists,

            'file_name' =>
                'prescription-' .
                $dispensing->prescription_id .
                '-dispensing-' .
                $dispensing->id .
                '.pdf',

            'download_url' =>
                $fileExists
                    ? url(
                        '/api/pharmacy/prescription-dispensings/' .
                        $dispensing->id .
                        '/archive?pharmacy_id=' .
                        $dispensing->pharmacy_id
                    )
                    : null,

            'pdf_hash' =>
                $dispensing->pdf_hash,

            'path' =>
                $dispensing->archived_pdf_path,
        ];
    });

    return $dispensings;
}
}