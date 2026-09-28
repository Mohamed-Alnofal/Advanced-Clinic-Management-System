<?php

namespace Modules\MedicalRecords\Support;

use Modules\MedicalRecords\Models\MedicalReferenceRange;
use Modules\MedicalRecords\Services\MedicalReferenceRangeResolver;

/**
 * Enriches stored report items from reference_range rows when needed.
 * Re-evaluates "unknown" items when ranges were seeded after the first analysis.
 */
class MedicalRecordReportPresenter
{
    public function __construct(
        private readonly MedicalReferenceRangeResolver $resolver,
    ) {}

    public function present(
        ?array $report,
        ?string $gender = null,
        ?int $age = null,
    ): ?array {
        if ($report === null || ! isset($report['items']) || ! is_array($report['items'])) {
            return $report;
        }

        $gender = $this->resolver->normalizeGender($gender);

        $report['items'] = array_map(function (array $item) use ($gender, $age) {
            $needsRange = empty($item['reference_id'])
                || ($item['status'] ?? null) === 'unknown'
                || empty($item['name'])
                || empty($item['unit']);

            if ($needsRange) {
                $item = $this->reEvaluateItem($item, $gender, $age);
            }

            if (! empty($item['name']) && ! empty($item['unit']) && ! empty($item['reference_id'])) {
                return $item;
            }

            $referenceId = $item['reference_id'] ?? null;
            if ($referenceId === null) {
                return $item;
            }

            $reference = MedicalReferenceRange::query()->find($referenceId);
            if ($reference === null) {
                return $item;
            }

            $item['name'] = $item['name'] ?? $reference->test_name;
            $item['unit'] = $item['unit'] ?? $reference->unit;
            $item['reference_range'] = $item['reference_range'] ?? $reference->range;
            $item['key'] = $item['key'] ?? $this->baseKeyFromTestKey($reference->test_key);

            return $item;
        }, $report['items']);

        $report['summary'] = $this->rebuildSummary($report['items']);
        $report['conclusion'] = $this->rebuildConclusion($report['summary']);

        return $report;
    }

    private function reEvaluateItem(array $item, ?string $gender, ?int $age): array
    {
        $key = isset($item['key']) ? strtolower(trim((string) $item['key'])) : '';
        $value = $item['value'] ?? null;

        if ($key === '' || $value === null || ! is_numeric($value)) {
            return $item;
        }

        $reference = $this->resolver->resolve($key, $gender, $age);
        if ($reference === null) {
            return $item;
        }

        $evaluation = $reference->evaluate(
            (float) $value,
            $gender ?? 'all',
            $age,
        );

        return [
            ...$item,
            'key' => $key,
            'reference_id' => $reference->id,
            'value' => (float) $value,
            'status' => $evaluation['status'],
            'message' => $evaluation['message'],
            'advice' => $evaluation['advice'],
            'name' => $reference->test_name,
            'unit' => $reference->unit,
            'reference_range' => $reference->range,
        ];
    }

    private function rebuildSummary(array $items): array
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
            } else {
                $summary['unknown']++;
            }
        }

        return $summary;
    }

    private function rebuildConclusion(array $summary): string
    {
        $abnormal = ($summary['high'] ?? 0) + ($summary['low'] ?? 0);

        if (($summary['total_tests'] ?? 0) === 0) {
            return 'لم يتم العثور على قيم قابلة للتحليل في الملف.';
        }

        if ($abnormal === 0 && ($summary['unknown'] ?? 0) === 0) {
            return 'جميع القيم المستخرجة ضمن النطاق الطبيعي.';
        }

        if ($abnormal > 0) {
            return "تم العثور على {$abnormal} نتيجة خارج النطاق الطبيعي. يُنصح بمراجعة الطبيب.";
        }

        return 'تم تحليل التقرير. بعض النتائج تحتاج مراجعة يدوية.';
    }

    private function baseKeyFromTestKey(string $testKey): string
    {
        return preg_replace('/_(male|female)$/', '', strtolower($testKey)) ?? $testKey;
    }
}
