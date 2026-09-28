<?php

namespace Modules\MedicalRecords\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\MedicalRecords\Models\MedicalRecord;
use Smalot\PdfParser\Parser;

class MedicalAnalysisService
{
    public function __construct(
        private readonly MedicalReferenceRangeResolver $referenceResolver,
    ) {}

    public function analyze(MedicalRecord $record): array
    {
        $text = $this->extractText($record);
        $patient = $record->patient;
        $gender = $this->referenceResolver->normalizeGender($patient?->gender);
        $age = $patient?->age !== null ? (int) $patient->age : null;

        return $this->analyzeText($text, $gender, $age);
    }

    /**
     * Lab assistant / ad-hoc PDF analysis without a MedicalRecord row.
     */
    public function analyzeFilePath(string $path, ?string $gender = null, ?int $age = null): array
    {
        $text = $this->extractTextFromPath($path);

        return $this->analyzeText(
            $text,
            $this->referenceResolver->normalizeGender($gender),
            $age,
        );
    }

    public function analyzeText(string $text, ?string $gender = null, ?int $age = null): array
    {
        $rawTests = $this->extractMedicalValues($text);
        $evaluated = $this->evaluateTests($rawTests, $gender, $age);
        $report = $this->buildReport($evaluated);

        return [
            'report' => $report,
            'extracted_data' => [
                'tests' => $rawTests,
                'meta' => [
                    'text_chars' => strlen($text),
                    'gender_used' => $gender,
                    'age_used' => $age,
                ],
            ],
        ];
    }

    protected function extractText(MedicalRecord $record): string
    {
        $path = Storage::disk('public')->path($record->file_path);

        return $this->extractTextFromPath($path, $record->id);
    }

    protected function extractTextFromPath(string $path, int|string|null $recordId = null): string
    {
        if (! file_exists($path)) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'txt') {
            $content = @file_get_contents($path);

            return is_string($content) ? trim($content) : '';
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($path);

            return trim($pdf->getText());
        } catch (\Throwable $e) {
            Log::error('PDF Parser Error', [
                'record_id' => $recordId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Regex extraction only — returns compact {key, value} rows.
     */
    protected function extractMedicalValues(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $patterns = [
            'hemoglobin' => '/(?:Hemoglobin|Hb|الهيموجلوبين|هيموغلوبين)\s*[:\-]?\s*([\d\.]+)/iu',
            'hematocrit' => '/(?:Hematocrit|Hct|PCV|الهيماتوكريت)\s*[:\-]?\s*([\d\.]+)/iu',
            'wbc' => '/(?:WBC|White Blood Cells|كريات الدم البيضاء|كرات الدم البيضاء)\s*[:\-]?\s*([\d\.]+)/iu',
            'platelets' => '/(?:Platelets|PLT|الصفائح الدموية)\s*[:\-]?\s*([\d\.]+)/iu',
            'rbc' => '/(?:RBC|Red Blood Cells|كريات الدم الحمراء|كرات الدم الحمراء)\s*[:\-]?\s*([\d\.]+)/iu',
            'mcv' => '/(?:MCV)\s*[:\-]?\s*([\d\.]+)/iu',
            'mch' => '/(?:MCH)(?!C)\s*[:\-]?\s*([\d\.]+)/iu',
            'mchc' => '/(?:MCHC)\s*[:\-]?\s*([\d\.]+)/iu',
            'glucose' => '/(?:Glucose|FBS|سكر الدم|سكر صائم|الجلوكوز)\s*[:\-]?\s*([\d\.]+)/iu',
            'creatinine' => '/(?:Creatinine|الكرياتينين|كرياتينين)\s*[:\-]?\s*([\d\.]+)/iu',
            'urea' => '/(?:Urea|BUN|اليوريا)\s*[:\-]?\s*([\d\.]+)/iu',
            'crp' => '/(?:CRP|بروتين سي التفاعلي)\s*[:\-]?\s*([\d\.]+)/iu',
            'cholesterol' => '/(?:Total\s*Cholesterol|Cholesterol|الكوليسترول(?:\s*الكلي)?)\s*[:\-]?\s*([\d\.]+)/iu',
            'hdl' => '/(?:HDL(?:-C)?|كوليسترول\s*HDL)\s*[:\-]?\s*([\d\.]+)/iu',
            'ldl' => '/(?:LDL(?:-C)?|كوليسترول\s*LDL)\s*[:\-]?\s*([\d\.]+)/iu',
            'triglycerides' => '/(?:Triglycerides|TG|الدهون الثلاثية)\s*[:\-]?\s*([\d\.]+)/iu',
            'alt' => '/(?:ALT|SGPT|ألانين)\s*[:\-]?\s*([\d\.]+)/iu',
            'ast' => '/(?:AST|SGOT|أسبارتات)\s*[:\-]?\s*([\d\.]+)/iu',
            'tsh' => '/(?:TSH|الهرمون المحفز للدرق)\s*[:\-]?\s*([\d\.]+)/iu',
            'vitamin_d' => '/(?:Vitamin\s*D(?:3)?|25(?:-|\s*)OH(?:-|\s*)D|فيتامين\s*د)\s*[:\-]?\s*([\d\.]+)/iu',
            'pt' => '/(?:\bPT\b|Prothrombin Time|زمن البروثرومبين)\s*[:\-]?\s*([\d\.]+)/iu',
            'inr' => '/(?:\bINR\b)\s*[:\-]?\s*([\d\.]+)/iu',
        ];

        $results = [];

        foreach ($patterns as $key => $regex) {
            if (! preg_match($regex, $text, $matches)) {
                continue;
            }

            $results[] = [
                'key' => $key,
                'value' => (float) $matches[1],
            ];
        }

        return $results;
    }

    protected function evaluateTests(
        array $rawTests,
        ?string $gender,
        ?int $age,
    ): array {
        $items = [];

        foreach ($rawTests as $test) {
            $key = $test['key'] ?? null;
            $value = $test['value'] ?? null;

            if ($key === null || $value === null) {
                continue;
            }

            $reference = $this->referenceResolver->resolve($key, $gender, $age);

            if ($reference === null) {
                $items[] = [
                    'key' => $key,
                    'value' => $value,
                    'status' => 'unknown',
                    'message' => 'لا يوجد نطاق مرجعي لهذا الفحص',
                    'advice' => 'يرجى إدخال المجال المرجعي لهذا الفحص',
                ];

                continue;
            }

            $evaluation = $reference->evaluate(
                (float) $value,
                $gender ?? 'all',
                $age,
            );

            $items[] = [
                'key' => $key,
                'reference_id' => $reference->id,
                'value' => $value,
                'status' => $evaluation['status'],
                'message' => $evaluation['message'],
                'advice' => $evaluation['advice'],
                // Display fields resolved from reference_id via presenter when needed.
                'name' => $reference->test_name,
                'unit' => $reference->unit,
                'reference_range' => $reference->range,
            ];
        }

        return $items;
    }

    protected function buildReport(array $items): array
    {
        $summary = [
            'total_tests' => count($items),
            'normal' => 0,
            'low' => 0,
            'high' => 0,
            'unknown' => 0,
        ];

        foreach ($items as $item) {
            $status = $item['status'] ?? 'unknown';
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        $conclusion = count($items) === 0
            ? 'لم يتم العثور على قيم قابلة للتحليل في الملف.'
            : $this->buildConclusion($summary);

        return [
            'summary' => $summary,
            'items' => $items,
            'conclusion' => $conclusion,
        ];
    }

    protected function buildConclusion(array $summary): string
    {
        $abnormal = ($summary['high'] ?? 0) + ($summary['low'] ?? 0);

        if ($abnormal === 0 && ($summary['unknown'] ?? 0) === 0) {
            return 'جميع القيم المستخرجة ضمن النطاق الطبيعي.';
        }

        if ($abnormal > 0) {
            return "تم العثور على {$abnormal} نتيجة خارج النطاق الطبيعي. يُنصح بمراجعة الطبيب.";
        }

        return 'تم تحليل التقرير. بعض النتائج تحتاج مراجعة يدوية.';
    }
}
