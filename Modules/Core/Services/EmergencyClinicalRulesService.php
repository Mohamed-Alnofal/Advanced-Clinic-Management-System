<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\File;

class EmergencyClinicalRulesService
{
    /** @var array<string, array>|null */
    private ?array $protocols = null;

    public function __construct(
        private EmergencySynonymExpander $synonymExpander,
    ) {}

    public function scoreMatch(string $transcript): ?array
    {
        $ranked = $this->rankMatches($transcript);

        return $ranked[0] ?? null;
    }

    /**
     * Rank all protocol matches (synonym-expanded) highest score first.
     *
     * @return list<array{score: int, category: string, protocol: array}>
     */
    public function rankMatches(string $transcript): array
    {
        $text = $this->synonymExpander->expandForMatching($transcript);
        $matches = [];

        foreach ($this->protocols() as $category => $protocol) {
            if ($category === 'unknown') {
                continue;
            }

            $score = $this->calculateScore($text, $protocol['keywords'] ?? []);

            if ($score <= 0) {
                continue;
            }

            $matches[] = [
                'score' => $score,
                'category' => $category,
                'protocol' => $protocol,
            ];
        }

        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $matches;
    }

    /**
     * Override when score meets the protocol's own override_score (or global floor).
     */
    public function resolve(string $transcript, int $globalOverrideThreshold): ?array
    {
        $match = $this->scoreMatch($transcript);

        if ($match === null) {
            return null;
        }

        $protocolThreshold = (int) ($match['protocol']['override_score'] ?? $globalOverrideThreshold);
        // Raise the bar with the global config (tests/ops can force the AI path with a high value).
        // Protocol override_score never lowers the global floor.
        $threshold = max($globalOverrideThreshold, $protocolThreshold);

        if ($match['score'] < $threshold) {
            return null;
        }

        return $this->buildAnalysis($match['protocol'], $match['score'], 'clinical_rules', 'high');
    }

    /**
     * Soft hint enrichment — never blindly overwrite a confident AI answer.
     */
    public function enrich(string $transcript, array $analysis, int $hintThreshold): array
    {
        $match = $this->scoreMatch($transcript);

        if ($match === null || $match['score'] < $hintThreshold) {
            $analysis['rules_score'] = $analysis['rules_score'] ?? ($match['score'] ?? null);

            return $analysis;
        }

        $protocol = $match['protocol'];
        $source = (string) ($analysis['ai_source'] ?? '');
        $confidence = (string) ($analysis['ai_confidence'] ?? $analysis['confidence'] ?? 'medium');
        $category = $analysis['detected_category'] ?? null;
        $isWeak = in_array($source, ['fallback', 'fallback+rules'], true)
            || $confidence === 'low'
            || $category === null
            || $category === 'unknown'
            || $category === '';

        $analysis['rules_score'] = $match['score'];

        if ($isWeak) {
            $analysis['detected_category'] = $protocol['category'] ?? $category;
            if (empty($analysis['suspected_condition']) || $category === null || $category === 'unknown') {
                $analysis['suspected_condition'] = $protocol['suspected_condition']
                    ?? $analysis['suspected_condition']
                    ?? null;
            }
            if (empty($analysis['immediate_first_aid_steps']) && ! empty($protocol['first_aid_steps'])) {
                $analysis['immediate_first_aid_steps'] = $protocol['first_aid_steps'];
            }
            $analysis['severity_level'] = $protocol['severity_level']
                ?? $analysis['severity_level']
                ?? null;
            $analysis['warning_signs'] = ! empty($analysis['warning_signs'])
                ? $analysis['warning_signs']
                : ($protocol['warning_signs'] ?? []);
            $analysis['do_not'] = ! empty($analysis['do_not'])
                ? $analysis['do_not']
                : ($protocol['do_not'] ?? []);
        } else {
            // Confident AI: only fill gaps, never overwrite severity/category.
            if ($category === null || $category === 'unknown') {
                $analysis['detected_category'] = $protocol['category'] ?? $category;
            }
            if (empty($analysis['warning_signs'])) {
                $analysis['warning_signs'] = $protocol['warning_signs'] ?? [];
            }
            if (empty($analysis['do_not'])) {
                $analysis['do_not'] = $protocol['do_not'] ?? [];
            }
        }

        $wrongTerms = ['احتقان', 'ذبح قلبي', 'ذبحه قلبي'];
        $condition = mb_strtolower((string) ($analysis['suspected_condition'] ?? ''));

        foreach ($wrongTerms as $term) {
            if ($condition !== '' && mb_strpos($condition, $term) !== false) {
                $analysis['suspected_condition'] = $protocol['suspected_condition'];
                break;
            }
        }

        if (($analysis['ai_confidence'] ?? 'medium') !== 'high' && $match['score'] >= 85) {
            $analysis['ai_confidence'] = 'high';
        }

        return $analysis;
    }

    public function protocolForCategory(?string $category): ?array
    {
        if ($category === null || $category === '') {
            return null;
        }

        return $this->protocols()[$category] ?? null;
    }

    public function buildFromMatch(array $match, string $source, string $confidence): array
    {
        return $this->buildAnalysis($match['protocol'], $match['score'], $source, $confidence);
    }

    /** @return array<string, array> */
    public function protocols(): array
    {
        if ($this->protocols !== null) {
            return $this->protocols;
        }

        $path = module_path('Core', 'Data/emergency_protocols.json');
        $contents = File::get($path);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Emergency protocols JSON is invalid.');
        }

        return $this->protocols = $decoded;
    }

    private function calculateScore(string $text, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword => $weight) {
            if (mb_strpos($text, mb_strtolower((string) $keyword)) !== false) {
                $score += (int) $weight;
            }
        }

        return min($score, 100);
    }

    private function buildAnalysis(array $protocol, int $score, string $source, string $confidence): array
    {
        return [
            'detected_category' => $protocol['category'] ?? null,
            'severity_level' => $protocol['severity_level'],
            'suspected_condition' => $protocol['suspected_condition'],
            'immediate_first_aid_steps' => $protocol['first_aid_steps'],
            'warning_signs' => $protocol['warning_signs'] ?? [],
            'do_not' => $protocol['do_not'] ?? [],
            'ai_confidence' => $confidence,
            'ai_source' => $source,
            'rules_score' => $score,
            'call_emergency_now' => ($protocol['severity_level'] ?? '') === 'Critical',
        ];
    }
}
