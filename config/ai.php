<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    | The preferred provider key. In dev/test (no API keys) the manager falls
    | through to the always-available 'fake' provider.
    */
    'default_provider' => env('AI_PROVIDER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Order
    |--------------------------------------------------------------------------
    | The AiProviderManager walks this list in order, skipping any provider
    | that is not "available" (i.e. has no API key). 'fake' is always last and
    | always available, guaranteeing a terminal fallback so the AI never hard
    | fails (P-05 Progressive Enhancement).
    */
    'fallback_order' => ['gemini', 'claude', 'openai', 'fake'],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    | A provider is "available" only if its api_key is set. 'fake' needs none.
    */
    'providers' => [
        'gemini' => [
            'api_key'  => env('GEMINI_API_KEY'),
            'model'    => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'base_uri' => env('GEMINI_BASE_URI', 'https://generativelanguage.googleapis.com'),
        ],
        'claude' => [
            'api_key'  => env('ANTHROPIC_API_KEY'),
            'model'    => env('CLAUDE_MODEL', 'claude-3-5-sonnet'),
            'base_uri' => env('CLAUDE_BASE_URI', 'https://api.anthropic.com'),
        ],
        'openai' => [
            'api_key'  => env('OPENAI_API_KEY'),
            'model'    => env('OPENAI_MODEL', 'gpt-4o'),
            'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com'),
        ],
        'fake' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    | Used by RetrievalService for RAG similarity ranking. The 'fake' embedding
    | provider is deterministic + always available; dimensions kept small.
    */
    'embedding' => [
        'provider'   => env('AI_EMBEDDING', 'fake'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 64),
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Store
    |--------------------------------------------------------------------------
    | 'database' uses RetrievalService over the relational store (the working
    | dev path). 'qdrant' is a config-gated stub for production.
    */
    'vector_store' => env('AI_VECTOR_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Retrieval (RAG)
    |--------------------------------------------------------------------------
    */
    'retrieval' => [
        'top_k'          => (int) env('AI_RETRIEVAL_TOP_K', 5),
        'excerpt_length' => 160,
    ],
];
