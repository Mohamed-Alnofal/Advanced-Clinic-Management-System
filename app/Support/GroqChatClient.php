<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Groq chat with ordered model failover (GROQ_MODELS).
 */
class GroqChatClient
{
    private const DEFAULT_TIMEOUT_SECONDS = 28;

    /** @return list<string> */
    public function modelChain(): array
    {
        $models = config('services.groq.models');
        if (is_array($models) && $models !== []) {
            $out = [];
            foreach ($models as $model) {
                $model = trim((string) $model);
                if ($model !== '' && ! in_array($model, $out, true)) {
                    $out[] = $model;
                }
            }

            return $out;
        }

        $single = trim((string) config('services.groq.model', 'openai/gpt-oss-120b'));

        return $single !== '' ? [$single] : [];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages, ?int $timeoutSeconds = null): string
    {
        $apiKey = trim((string) config('services.groq.key', ''));
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

        if ($apiKey === '' || str_contains(strtoupper($apiKey), 'PASTE_YOUR')) {
            throw new RuntimeException('GROQ_API_KEY is not configured.');
        }

        $chain = $this->modelChain();
        if ($chain === []) {
            throw new RuntimeException('No Groq models configured (set GROQ_MODELS).');
        }

        $timeout = $timeoutSeconds ?? self::DEFAULT_TIMEOUT_SECONDS;
        $errors = [];

        foreach ($chain as $model) {
            try {
                return $this->completeWithModel($apiKey, $baseUrl, $model, $messages, $timeout);
            } catch (Throwable $e) {
                $errors[] = "{$model}: ".$e->getMessage();
                if (! $this->shouldFailoverToNextModel($e)) {
                    throw $e;
                }
                Log::warning('Groq model failed, trying next in chain', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('All Groq models failed: '.implode(' | ', $errors));
    }

    /**
     * @return array{
     *   ok: bool,
     *   detail: string,
     *   model_chain: list<string>,
     *   models: array<string, array{ok: bool, detail: string}>
     * }
     */
    public function probeHealth(): array
    {
        $chain = $this->modelChain();
        $apiKey = trim((string) config('services.groq.key', ''));

        if ($apiKey === '' || str_contains(strtoupper($apiKey), 'PASTE_YOUR')) {
            return [
                'ok' => false,
                'detail' => 'GROQ_API_KEY missing',
                'model_chain' => $chain,
                'models' => [],
            ];
        }

        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $response = Http::timeout(8)
            ->connectTimeout(5)
            ->withToken($apiKey)
            ->acceptJson()
            ->get("{$baseUrl}/models");

        if (! $response->successful()) {
            return [
                'ok' => false,
                'detail' => 'HTTP '.$response->status().' listing models',
                'model_chain' => $chain,
                'models' => [],
            ];
        }

        $available = collect(data_get($response->json(), 'data', []))
            ->pluck('id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();

        $checks = [];
        foreach ($chain as $model) {
            $checks[$model] = in_array($model, $available, true)
                ? ['ok' => true, 'detail' => 'available']
                : ['ok' => false, 'detail' => 'not listed by Groq API'];
        }

        $anyOk = collect($checks)->contains(fn ($row) => ($row['ok'] ?? false) === true);
        $firstOk = collect($chain)->first(fn ($model) => ($checks[$model]['ok'] ?? false) === true);

        return [
            'ok' => $anyOk,
            'detail' => $anyOk
                ? 'reachable (primary: '.$firstOk.')'
                : 'no configured models available — update GROQ_MODELS',
            'model_chain' => $chain,
            'models' => $checks,
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function completeWithModel(
        string $apiKey,
        string $baseUrl,
        string $model,
        array $messages,
        int $timeout
    ): string {
        try {
            return $this->request($apiKey, $baseUrl, $model, $messages, $timeout, true);
        } catch (Throwable $e) {
            Log::info('Groq JSON mode failed, retrying without response_format', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return $this->request($apiKey, $baseUrl, $model, $messages, $timeout, false);
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function request(
        string $apiKey,
        string $baseUrl,
        string $model,
        array $messages,
        int $timeout,
        bool $jsonMode
    ): string {
        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => $messages,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout($timeout)
            ->connectTimeout(8)
            ->withToken($apiKey)
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Groq HTTP '.$response->status().': '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Groq returned an empty response.');
        }

        return $content;
    }

    private function shouldFailoverToNextModel(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, '404')
            || str_contains($msg, 'model')
            || str_contains($msg, 'not found')
            || str_contains($msg, '429')
            || str_contains($msg, 'rate limit')
            || str_contains($msg, '503')
            || str_contains($msg, '502');
    }
}
