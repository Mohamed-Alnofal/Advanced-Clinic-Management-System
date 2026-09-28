<?php

namespace Modules\Core\Services;

/**
 * RAG retrieval for emergency protocols.
 * Prefers semantic embeddings when available; falls back to synonym-aware keyword ranking.
 */
class EmergencyProtocolRetriever
{
    private string $lastRetrievalMode = 'none';

    public function __construct(
        private EmergencyClinicalRulesService $clinicalRules,
        private EmergencyEmbeddingService $embeddings,
    ) {}

    public function lastRetrievalMode(): string
    {
        return $this->lastRetrievalMode;
    }

    /**
     * @return list<array{category: string, score: int|float, severity_level: string|null, suspected_condition: string|null, retrieval: string, first_aid_steps?: list<string>, warning_signs?: list<string>, do_not?: list<string>}>
     */
    public function topCandidates(string $transcript, int $limit = 3): array
    {
        $limit = max(1, $limit);
        $semantic = $this->semanticCandidates($transcript, $limit);

        if ($semantic !== []) {
            $this->lastRetrievalMode = 'embeddings';

            return $semantic;
        }

        $this->lastRetrievalMode = 'keywords';

        return $this->keywordCandidates($transcript, $limit);
    }

    public function formatForPrompt(string $transcript, int $limit = 3): string
    {
        $candidates = $this->topCandidates($transcript, $limit);

        if ($candidates === []) {
            $this->lastRetrievalMode = 'none';

            return 'لا توجد مرشّحات سريرية قوية — استخدم unknown إذا لم تتضح الفئة.';
        }

        $lines = [];
        foreach ($candidates as $index => $candidate) {
            $n = $index + 1;
            $lines[] = sprintf(
                '%d) category=%s score=%s via=%s severity=%s condition=%s',
                $n,
                $candidate['category'],
                is_float($candidate['score']) ? number_format($candidate['score'], 3) : $candidate['score'],
                $candidate['retrieval'] ?? 'keywords',
                $candidate['severity_level'] ?? 'n/a',
                $candidate['suspected_condition'] ?? 'n/a'
            );
        }

        // Top candidate full protocol chunk for grounded advisory (classification still sees thin list).
        $top = $candidates[0];
        $full = $this->clinicalRules->protocolForCategory($top['category'] ?? '');
        if ($full !== null) {
            $lines[] = 'أفضل بروتوكول كامل للاعتماد عند التطابق: '.json_encode([
                'category' => $top['category'],
                'severity_level' => $full['severity_level'] ?? null,
                'suspected_condition' => $full['suspected_condition'] ?? null,
                'first_aid_steps' => $full['first_aid_steps'] ?? [],
                'warning_signs' => $full['warning_signs'] ?? [],
                'do_not' => $full['do_not'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
        }

        return "مرشّحات سريرية مسترجعة (فضّل الأعلى إن ناسب الوصف):\n".implode("\n", $lines);
    }

    /**
     * Full top protocol for Stage-2 advisory grounding.
     */
    public function topProtocolChunk(string $transcript): ?array
    {
        $candidates = $this->topCandidates($transcript, 1);
        if ($candidates === []) {
            return null;
        }

        return $this->clinicalRules->protocolForCategory($candidates[0]['category']);
    }

    /**
     * @return list<array{category: string, score: float, severity_level: string|null, suspected_condition: string|null, retrieval: string}>
     */
    private function semanticCandidates(string $transcript, int $limit): array
    {
        if (! config('services.emergency_ai.embeddings_enabled', true)) {
            return [];
        }

        if (! $this->embeddings->isAvailable()) {
            return [];
        }

        $documents = [];
        foreach ($this->clinicalRules->protocols() as $category => $protocol) {
            if ($category === 'unknown') {
                continue;
            }
            $documents[$category] = $this->protocolSearchText($protocol);
        }

        $ranked = $this->embeddings->rankBySimilarity($transcript, $documents, $limit);
        $minScore = (float) config('services.emergency_ai.embedding_min_score', 0.35);
        $out = [];

        foreach ($ranked as $row) {
            if ($row['score'] < $minScore) {
                continue;
            }
            $protocol = $this->clinicalRules->protocolForCategory($row['category']);
            if ($protocol === null) {
                continue;
            }
            $out[] = [
                'category' => $row['category'],
                'score' => $row['score'],
                'severity_level' => $protocol['severity_level'] ?? null,
                'suspected_condition' => $protocol['suspected_condition'] ?? null,
                'retrieval' => 'embeddings',
                'first_aid_steps' => $protocol['first_aid_steps'] ?? [],
                'warning_signs' => $protocol['warning_signs'] ?? [],
                'do_not' => $protocol['do_not'] ?? [],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{category: string, score: int, severity_level: string|null, suspected_condition: string|null, retrieval: string}>
     */
    private function keywordCandidates(string $transcript, int $limit): array
    {
        $ranked = $this->clinicalRules->rankMatches($transcript);

        return array_slice(array_map(static function (array $match): array {
            $protocol = $match['protocol'];

            return [
                'category' => $match['category'],
                'score' => $match['score'],
                'severity_level' => $protocol['severity_level'] ?? null,
                'suspected_condition' => $protocol['suspected_condition'] ?? null,
                'retrieval' => 'keywords',
                'first_aid_steps' => $protocol['first_aid_steps'] ?? [],
                'warning_signs' => $protocol['warning_signs'] ?? [],
                'do_not' => $protocol['do_not'] ?? [],
            ];
        }, $ranked), 0, $limit);
    }

    private function protocolSearchText(array $protocol): string
    {
        $keywords = array_keys($protocol['keywords'] ?? []);

        return trim(implode(' ', [
            $protocol['category'] ?? '',
            $protocol['suspected_condition'] ?? '',
            implode(' ', $keywords),
            implode(' ', $protocol['warning_signs'] ?? []),
            implode(' ', $protocol['first_aid_steps'] ?? []),
        ]));
    }
}
