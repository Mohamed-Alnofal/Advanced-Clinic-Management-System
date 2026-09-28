<?php

namespace Modules\Core\Services;

class EmergencyStepValidator
{
    /** @var array<string, list<string>> */
    private const BLOCKED_PATTERNS = [
        'choking' => ['ماء', 'شرب', 'طعام', 'شراب'],
        'cardiac' => ['يمشي', 'مشي', 'مجهود'],
        'stroke' => ['ماء', 'طعام', 'دواء'],
        'unconscious' => ['ماء', 'طعام', 'شراب'],
        'poisoning' => ['قيء', 'تقيؤ'],
        'seizure' => ['فم', 'لسان'],
    ];

    /** @var array<string, list<string>> */
    private const GLOBAL_BLOCKED = [
        'اتبع تعليمات الطبيب',
        'حسب الحالة',
        'إذا سمح الطبيب',
    ];

    public function sanitize(array $steps, ?string $category = null): array
    {
        $blocked = self::GLOBAL_BLOCKED;

        if ($category !== null && isset(self::BLOCKED_PATTERNS[$category])) {
            $blocked = array_merge($blocked, self::BLOCKED_PATTERNS[$category]);
        }

        $sanitized = [];

        foreach ($steps as $step) {
            $step = trim((string) $step);

            if ($step === '' || $this->containsBlockedPattern($step, $blocked, $category)) {
                continue;
            }

            $sanitized[] = $step;
        }

        return array_values($sanitized);
    }

    private function containsBlockedPattern(string $step, array $blocked, ?string $category): bool
    {
        $lower = mb_strtolower($step);

        foreach ($blocked as $pattern) {
            if (mb_strpos($lower, mb_strtolower($pattern)) !== false) {
                if ($category === 'poisoning' && mb_strpos($lower, 'لا') !== false) {
                    continue;
                }

                if ($category === 'choking' && mb_strpos($lower, 'لا') !== false) {
                    continue;
                }

                if (in_array($pattern, self::GLOBAL_BLOCKED, true)) {
                    return true;
                }

                if ($category !== null && mb_strpos($lower, 'لا') === false) {
                    return true;
                }
            }
        }

        return false;
    }
}
