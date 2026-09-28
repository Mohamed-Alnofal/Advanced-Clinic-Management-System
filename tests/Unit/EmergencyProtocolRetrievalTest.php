<?php

namespace Tests\Unit;

use Modules\Core\Services\EmergencyClinicalRulesService;
use Modules\Core\Services\EmergencyProtocolRetriever;
use Modules\Core\Services\EmergencySynonymExpander;
use Tests\TestCase;

class EmergencyProtocolRetrievalTest extends TestCase
{
    public function test_synonym_expander_maps_dialect_cardiac_phrase(): void
    {
        /** @var EmergencySynonymExpander $expander */
        $expander = app(EmergencySynonymExpander::class);

        $expanded = $expander->expandForMatching('قلبي عم يوجعني وتعرق بارد');

        $this->assertStringContainsString('ألم صدر', $expanded);
    }

    public function test_synonym_aware_rules_match_choking_dialect(): void
    {
        /** @var EmergencyClinicalRulesService $rules */
        $rules = app(EmergencyClinicalRulesService::class);

        $match = $rules->scoreMatch('عم يشرق وما عم يقدر يتنفس');

        $this->assertNotNull($match);
        $this->assertSame('choking', $match['category']);
        $this->assertGreaterThanOrEqual(40, $match['score']);
    }

    public function test_protocol_retriever_returns_ranked_candidates(): void
    {
        /** @var EmergencyProtocolRetriever $retriever */
        $retriever = app(EmergencyProtocolRetriever::class);

        $candidates = $retriever->topCandidates('ألم شديد في الصدر وضيق تنفس', 3);

        $this->assertNotEmpty($candidates);
        $this->assertSame('cardiac', $candidates[0]['category']);
        $this->assertArrayHasKey('score', $candidates[0]);
        $this->assertArrayHasKey('suspected_condition', $candidates[0]);

        $prompt = $retriever->formatForPrompt('ألم شديد في الصدر وضيق تنفس', 2);
        $this->assertStringContainsString('مرشّحات سريرية', $prompt);
        $this->assertStringContainsString('cardiac', $prompt);
    }
}
