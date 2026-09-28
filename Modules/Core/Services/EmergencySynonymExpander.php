<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\File;

/**
 * Arabic dialect/synonym layer for emergency triage matching.
 * Expands transcripts so clinical keyword scoring and RAG-lite retrieval work better.
 */
class EmergencySynonymExpander
{
    /** @var array{replacements: array<string, string>, aliases: array<string, list<string>>}|null */
    private ?array $lexicon = null;

    /**
     * Apply replacements then append matched canonical tokens for scoring.
     */
    public function expandForMatching(string $transcript): string
    {
        $text = $this->applyReplacements($transcript);
        $tokens = $this->matchedCanonicalTokens($text);

        if ($tokens === []) {
            return $text;
        }

        return trim($text.' '.implode(' ', $tokens));
    }

    /**
     * Dialect → canonical replacements only (safe for LLM prompts).
     */
    public function applyReplacements(string $transcript): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $transcript) ?? $transcript);
        $text = mb_strtolower($text);

        foreach ($this->lexicon()['replacements'] as $search => $replace) {
            $text = str_replace(mb_strtolower((string) $search), (string) $replace, $text);
        }

        return trim($text);
    }

    /** @return list<string> */
    public function matchedCanonicalTokens(string $text): array
    {
        $haystack = mb_strtolower($text);
        $matched = [];

        foreach ($this->lexicon()['aliases'] as $canonical => $aliases) {
            $canonicalLower = mb_strtolower((string) $canonical);
            $hit = mb_strpos($haystack, $canonicalLower) !== false;

            if (! $hit) {
                foreach ($aliases as $alias) {
                    if (mb_strpos($haystack, mb_strtolower((string) $alias)) !== false) {
                        $hit = true;
                        break;
                    }
                }
            }

            if ($hit) {
                $matched[] = $canonicalLower;
            }
        }

        return array_values(array_unique($matched));
    }

    /** @return array{replacements: array<string, string>, aliases: array<string, list<string>>} */
    public function lexicon(): array
    {
        if ($this->lexicon !== null) {
            return $this->lexicon;
        }

        $path = module_path('Core', 'Data/emergency_synonyms.json');
        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Emergency synonyms JSON is invalid.');
        }

        return $this->lexicon = [
            'replacements' => is_array($decoded['replacements'] ?? null) ? $decoded['replacements'] : [],
            'aliases' => is_array($decoded['aliases'] ?? null) ? $decoded['aliases'] : [],
        ];
    }
}
