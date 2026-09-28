<?php

namespace Modules\MedicalRecords\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\MedicalRecords\Models\MedicalReferenceRange;

/**
 * Resolves reference ranges from the indexed lookup table (small dataset).
 * One row per test variant (gender/age) — NOT duplicated per patient upload.
 */
class MedicalReferenceRangeResolver
{
    private const CACHE_KEY = 'medical_reference_ranges.all';
    private const CACHE_TTL = 3600;

    public function all(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return MedicalReferenceRange::query()->get();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

  /**
   * Find the best matching reference row for an extracted test key.
   *
   * Supports keys like "hemoglobin" with gender-specific rows (hemoglobin_male).
   */
    public function resolve(
        string $baseKey,
        ?string $gender = null,
        ?int $age = null,
    ): ?MedicalReferenceRange {
        $gender = $this->normalizeGender($gender);
        $baseKey = strtolower(trim($baseKey));

        $candidates = $this->all()->filter(function (MedicalReferenceRange $row) use ($baseKey) {
            $key = strtolower($row->test_key);

            return $key === $baseKey
                || $key === "{$baseKey}_male"
                || $key === "{$baseKey}_female";
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        $scored = $candidates
            ->map(fn (MedicalReferenceRange $row) => [
                'row' => $row,
                'score' => $this->matchScore($row, $gender, $age),
            ])
            ->filter(fn (array $entry) => $entry['score'] > 0)
            ->sortByDesc('score')
            ->values();

        return $scored->first()['row'] ?? null;
    }

    public function normalizeGender(?string $gender): ?string
    {
        if ($gender === null || trim($gender) === '') {
            return null;
        }

        $g = strtolower(trim($gender));

        if (in_array($g, ['male', 'm', 'ذكر', 'رجل'], true)) {
            return 'male';
        }

        if (in_array($g, ['female', 'f', 'أنثى', 'انثى', 'امرأة'], true)) {
            return 'female';
        }

        return $g;
    }

    private function matchScore(
        MedicalReferenceRange $row,
        ?string $gender,
        ?int $age,
    ): int {
        if ($gender !== null && $row->gender !== 'all' && $row->gender !== $gender) {
            return 0;
        }

        if ($age !== null) {
            if ($row->age_min !== null && $age < $row->age_min) {
                return 0;
            }
            if ($row->age_max !== null && $age > $row->age_max) {
                return 0;
            }
        }

        $score = 1;

        if ($gender !== null) {
            if ($row->gender === $gender) {
                $score += 20;
            } elseif ($row->gender === 'all') {
                $score += 10;
            }
        } elseif ($row->gender === 'all') {
            $score += 5;
        }

        if ($age !== null && ($row->age_min !== null || $row->age_max !== null)) {
            $score += 5;
        }

        return $score;
    }
}
