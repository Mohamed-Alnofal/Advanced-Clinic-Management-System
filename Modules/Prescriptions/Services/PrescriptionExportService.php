<?php

namespace Modules\Prescriptions\Services;

use Mpdf\Mpdf;
use Illuminate\Support\Facades\Gate;
use Modules\Prescriptions\Models\Prescriptions;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class PrescriptionExportService
{
    public function __construct(
        protected PrescriptionQrService $qrService
    ) {
    }

    /**
     * Export PDF as HTTP response.
     */
    public function exportPdf(int $id)
    {
        $pdf = $this->generatePdfContent($id);

        return response(
            $pdf['content'],
            200
        )
            ->header(
                'Content-Type',
                'application/pdf'
            )
            ->header(
                'Content-Disposition',
                'inline; filename="' . $pdf['filename'] . '"'
            )
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate'
            );
    }


    /**
     * Generate PDF content without returning HTTP response.
     *
     * This method is used by the pharmacy dispensing service
     * to archive the exact prescription PDF.
     *
     * Pass $authorize = false only after the caller has already
     * verified pharmacist ownership of the pharmacy.
     */
    public function generatePdfContent(int $id, bool $authorize = true): array
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Get prescription
        |--------------------------------------------------------------------------
        */

        $prescription = Prescriptions::with([
            'items',
            'visit.doctor.user',
            'visit.doctor.specialization',
            'visit.patientFile.patient.patientable',
        ])->findOrFail($id);


        /*
        |--------------------------------------------------------------------------
        | 2. Authorization
        |--------------------------------------------------------------------------
        */

        if ($authorize) {
            Gate::authorize(
                'exportPdf',
                $prescription
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Generate secure QR payload
        |--------------------------------------------------------------------------
        */

        $qrPayload = $this->qrService->generatePayload(
            $prescription->id
        );


        /*
        |--------------------------------------------------------------------------
        | 4. Generate QR as SVG
        |--------------------------------------------------------------------------
        |
        | SVG does not require Imagick.
        |
        */

        $qrSvg = $this->generateQrSvg($qrPayload);


        /*
        |--------------------------------------------------------------------------
        | 5. Generate HTML
        |--------------------------------------------------------------------------
        */

        $html = $this->buildHtml(
            $prescription,
            $qrSvg
        );


        /*
        |--------------------------------------------------------------------------
        | 6. Render PDF
        |--------------------------------------------------------------------------
        */

        $filename =
            'prescription-' .
            $prescription->id .
            '.pdf';

        $content = $this->renderPdf($html);

        return [
            'prescription' => $prescription,

            'filename' => $filename,

            'content' => $content,
        ];
    }

    private function generateQrSvg(string $payload): string
    {
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(220)
                ->margin(1)
                ->errorCorrection('M')
                ->generate($payload);

            return $this->inlineSvg($svg);
        }

        $renderer = new ImageRenderer(
            new RendererStyle(220, 1),
            new SvgImageBackEnd()
        );

        return $this->inlineSvg(
            (new Writer($renderer))->writeString($payload)
        );
    }

    private function inlineSvg(string $svg): string
    {
        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE[^>]*>/', '', $svg) ?? $svg;

        return trim($svg);
    }

    private function renderPdf(string $html): string
    {
        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'default_font' => 'dejavusans',
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);
            $mpdf->SetDirectionality('rtl');
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        }

        $dompdf = new \Dompdf\Dompdf([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'isFontSubsettingEnabled' => true,
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }


    /*
    |--------------------------------------------------------------------------
    | Build PDF HTML
    |--------------------------------------------------------------------------
    */

    private function buildHtml(
        Prescriptions $prescription,
        string $qrSvg
    ): string {

        $visit =
            $prescription->visit;


        $doctor =
            $visit?->doctor;


        $doctorName =
            $doctor?->user?->name
            ?? 'غير محدد';


        $specialization =
            $doctor?->specialization?->name
            ?? 'غير محدد';


        $patient =
            $visit
                ?->patientFile
                ?->patient;


        $patientName =
            $patient
                ?->patientable
                ?->name
                ?? 'غير محدد';


        $visitDate =
            $visit?->visit_date
                ?->format('Y-m-d H:i')
                ?? 'غير محدد';


        $dispensingType =
            $prescription->dispensing_type === 'one_time'
                ? 'تصرف مرة واحدة'
                : 'قابلة لإعادة الصرف';


        $html = '

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<style>

body {

    font-family: dejavusans;

    direction: rtl;

    text-align: right;

    font-size: 12px;

    line-height: 1.8;

}

.header {

    text-align: center;

    margin-bottom: 25px;

}

.title {

    font-size: 24px;

    font-weight: bold;

    margin-bottom: 5px;

}

.subtitle {

    font-size: 11px;

}

.info-table {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 20px;

}

.info-table td {

    border: 1px solid #444;

    padding: 8px;

}

.label {

    font-weight: bold;

}

.medicine-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 10px;

}

.medicine-table th {

    border: 1px solid #333;

    padding: 8px;

    background-color: #eeeeee;

    text-align: center;

    font-weight: bold;

}

.medicine-table td {

    border: 1px solid #333;

    padding: 8px;

    vertical-align: top;

}

.section-title {

    font-size: 16px;

    font-weight: bold;

    margin-top: 20px;

    margin-bottom: 8px;

}

.dispensing-box {

    margin-top: 15px;

    padding: 10px;

    border: 1px solid #444;

    font-weight: bold;

}

.qr-section {

    margin-top: 30px;

    text-align: center;

}

.qr-section svg {

    width: 160px;

    height: 160px;

}

.qr-title {

    font-weight: bold;

    font-size: 13px;

    margin-top: 8px;

}

.qr-description {

    font-size: 10px;

    color: #555;

    margin-top: 4px;

}

.footer {

    margin-top: 45px;

}

.signature {

    margin-top: 30px;

}

</style>

</head>

<body dir="rtl">


<div class="header">

    <div class="title">
        الوصفة الطبية
    </div>

    <div class="subtitle">
        Medical Prescription
    </div>

</div>


<table class="info-table">

    <tr>

        <td>

            <span class="label">
                رقم الوصفة:
            </span>

            ' .
            $this->escape(
                $prescription->id
            )
            . '

        </td>


        <td>

            <span class="label">
                تاريخ الزيارة:
            </span>

            ' .
            $this->escape(
                $visitDate
            )
            . '

        </td>

    </tr>


    <tr>

        <td>

            <span class="label">
                الطبيب:
            </span>

            ' .
            $this->escape(
                $doctorName
            )
            . '

        </td>


        <td>

            <span class="label">
                التخصص:
            </span>

            ' .
            $this->escape(
                $specialization
            )
            . '

        </td>

    </tr>


    <tr>

        <td colspan="2">

            <span class="label">
                المريض:
            </span>

            ' .
            $this->escape(
                $patientName
            )
            . '

        </td>

    </tr>

</table>


<div class="dispensing-box">

    نوع الصرف:

    ' .
    $this->escape(
        $dispensingType
    )
    . '

</div>


<div class="section-title">

    الأدوية

</div>


<table class="medicine-table">

<thead>

<tr>

    <th>
        الدواء
    </th>

    <th>
        الجرعة
    </th>

    <th>
        التكرار
    </th>

    <th>
        المدة
    </th>

    <th>
        التعليمات
    </th>

</tr>

</thead>


<tbody>
';


        foreach ($prescription->items as $item) {

            $html .= '

<tr>

    <td>
        ' .
        $this->escape(
            $item->medicine_name
        )
        . '
    </td>


    <td>
        ' .
        $this->escape(
            $item->dose
        )
        . '
    </td>


    <td>
        ' .
        $this->escape(
            $item->frequency
        )
        . '
    </td>


    <td>
        ' .
        $this->escape(
            $item->duration
        )
        . '
    </td>


    <td>
        ' .
        $this->escape(
            $item->instructions ?? '-'
        )
        . '
    </td>

</tr>

';
        }


        $html .= '

</tbody>

</table>


<div class="qr-section">

    ' . $qrSvg . '

    <div class="qr-title">
        رمز التحقق من الوصفة
    </div>

    <div class="qr-description">
        يستخدم هذا الرمز من قبل الصيدلية للتحقق من صحة الوصفة وصرفها.
    </div>

</div>


<div class="footer">

    <div>

        <strong>
            الطبيب:
        </strong>

        ' .
        $this->escape(
            $doctorName
        )
        . '

    </div>


    <div class="signature">

        توقيع الطبيب:

        __________________________

    </div>

</div>


</body>

</html>
';


        return $html;
    }


    /*
    |--------------------------------------------------------------------------
    | Escape HTML
    |--------------------------------------------------------------------------
    */

    private function escape(
        mixed $value
    ): string {

        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES |
            ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}