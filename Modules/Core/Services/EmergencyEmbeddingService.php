<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Semantic embeddings for emergency protocol retrieval (Gemini or OpenAI).
 * Falls back gracefully — callers should use keyword ranking when this returns null.
 */
class EmergencyEmbeddingService
{
    /** @var array<string, list<float>>|null */
    private ?array $protocolVectors = null;

    public function isAvailable(): bool
    {
        $provider = config('services.emergency_ai.provider', 'ollama');

        if ($provider === 'gemini' && ! empty(config('services.emergency_ai.gemini_api_key'))) {
            return true;
        }

        if ($provider === 'openai' && ! empty(config('services.emergency_ai.openai_api_key'))) {
            return true;
        }

        // Allow embeddings via Gemini even when chat provider is ollama, if key exists.
        return ! empty(config('services.emergency_ai.gemini_api_key'))
            || ! empty(config('services.emergency_ai.openai_api_key'));
    }

    /**
     * Rank protocol texts by cosine similarity to the transcript.
     *
     * @param  array<string, string>  $documents  category => searchable text
     * @return list<array{category: string, score: float}>
     */
    public function rankBySimilarity(string $query, array $documents, int $limit = 3): array
    {
        if ($documents === [] || ! $this->isAvailable()) {
            return [];
        }

        try {
            $queryVector = $this->embed($query);
            if ($queryVector === null) {
                return [];
            }

            $ranked = [];
            foreach ($this->protocolEmbeddings($documents) as $category => $vector) {
                $ranked[] = [
                    'category' => $category,
                    'score' => $this->cosineSimilarity($queryVector, $vector),
                ];
            }

            usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            return array_slice($ranked, 0, max(1, $limit));
        } catch (\Throwable $e) {
            Log::warning('Emergency embedding ranking failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** @param  array<string, string>  $documents */
    private function protocolEmbeddings(array $documents): array
    {
        if ($this->protocolVectors !== null) {
            return $this->protocolVectors;
        }

        $ttl = max(60, (int) config('services.emergency_ai.embedding_cache_ttl', 86400));
        $cacheKey = 'emergency_ai:protocol_embeddings:v1:'.md5(json_encode([
            'docs' => $documents,
            'gemini' => (string) config('services.emergency_ai.embedding_model'),
            'openai' => (string) config('services.emergency_ai.openai_embedding_model'),
            'has_gemini' => ! empty(config('services.emergency_ai.gemini_api_key')),
            'has_openai' => ! empty(config('services.emergency_ai.openai_api_key')),
        ]));

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $this->protocolVectors = $cached;
        }

        $vectors = [];
        foreach ($documents as $category => $text) {
            $vector = $this->embed($text);
            if ($vector !== null) {
                $vectors[$category] = $vector;
            }
        }

        if ($vectors !== []) {
            Cache::put($cacheKey, $vectors, $ttl);
        }

        return $this->protocolVectors = $vectors;
    }

    /** @return list<float>|null */
    private function embed(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (! empty(config('services.emergency_ai.gemini_api_key'))) {
            return $this->embedWithGemini($text);
        }

        if (! empty(config('services.emergency_ai.openai_api_key'))) {
            return $this->embedWithOpenAi($text);
        }

        return null;
    }

    /** @return list<float>|null */
    private function embedWithGemini(string $text): ?array
    {
        $apiKey = config('services.emergency_ai.gemini_api_key');
        $model = config('services.emergency_ai.embedding_model', 'text-embedding-004');

        $response = Http::timeout(15)
            ->acceptJson()
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$apiKey}",
                [
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                ]
            );

        $response->throw();

        $values = data_get($response->json(), 'embedding.values');

        return is_array($values) ? array_map('floatval', $values) : null;
    }

    /** @return list<float>|null */
    private function embedWithOpenAi(string $text): ?array
    {
        $apiKey = config('services.emergency_ai.openai_api_key');
        $model = config('services.emergency_ai.openai_embedding_model', 'text-embedding-3-small');

        $response = Http::timeout(15)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => $text,
            ]);

        $response->throw();

        $values = data_get($response->json(), 'data.0.embedding');

        return is_array($values) ? array_map('floatval', $values) : null;
    }

    /** @param  list<float>  $a @param  list<float>  $b */
    private function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
