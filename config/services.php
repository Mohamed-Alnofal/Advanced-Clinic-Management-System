<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook' => env('STRIPE_WEBHOOK_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'firebase' => [
    'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        // Single model (legacy). Prefer GROQ_MODELS for failover chain.
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        // Comma-separated failover order — tries next model on 404/rate-limit.
        'models' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'GROQ_MODELS',
                env(
                    'GROQ_MODEL',
                    'openai/gpt-oss-120b,openai/gpt-oss-20b,qwen/qwen3.6-27b'
                )
            ))
        ))),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
    ],

    /*
    | Medical AI chat (/api/v1/ai/chat) — cloud providers only on production.
    | Comma-separated order; Ollama is ignored (not available on Hostinger).
    */
    'medical_ai' => [
        'providers' => env('MEDICAL_AI_PROVIDERS', 'groq,gemini,openai'),
    ],

    'emergency_ai' => [
        'provider' => env('EMERGENCY_AI_PROVIDER', 'gemini'),
        // Ordered failover after primary (comma-separated): e.g. groq,openai
        'failover_providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('EMERGENCY_AI_FAILOVER', 'groq,openai'))
        ))),
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
        'ollama_model' => env('OLLAMA_MODEL', 'qwen2.5:7b'),
        'ollama_timeout' => (int) env('OLLAMA_TIMEOUT', 90),
        'ollama_enabled' => filter_var(env('OLLAMA_ENABLED', false), FILTER_VALIDATE_BOOL),
        'two_stage_enabled' => filter_var(env('EMERGENCY_AI_TWO_STAGE', true), FILTER_VALIDATE_BOOL),
        // Default OFF: LLM runs first; protocols only ground steps / fallback.
        'rules_first_enabled' => filter_var(env('EMERGENCY_RULES_FIRST', false), FILTER_VALIDATE_BOOL),
        'rules_override_score' => (int) env('EMERGENCY_RULES_OVERRIDE_SCORE', 85),
        'rules_hint_score' => (int) env('EMERGENCY_RULES_HINT_SCORE', 60),
        'category_conflict_score' => (int) env('EMERGENCY_CATEGORY_CONFLICT_SCORE', 70),
        'embeddings_enabled' => filter_var(env('EMERGENCY_AI_EMBEDDINGS', true), FILTER_VALIDATE_BOOL),
        'embedding_model' => env('EMERGENCY_EMBEDDING_MODEL', 'text-embedding-004'),
        'openai_embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'embedding_cache_ttl' => (int) env('EMERGENCY_EMBEDDING_CACHE_TTL', 86400),
        'embedding_min_score' => (float) env('EMERGENCY_EMBEDDING_MIN_SCORE', 0.35),
        'analytics_enabled' => filter_var(env('EMERGENCY_AI_ANALYTICS', true), FILTER_VALIDATE_BOOL),
    ],

];
