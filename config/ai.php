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
        'qwen' => [
            'api_key'  => env('QWEN_API_KEY'),
            'model'    => env('QWEN_MODEL', 'qwen2.5-72b-instruct'),
            'base_uri' => env('QWEN_BASE_URI', 'https://dashscope.aliyuncs.com'),
        ],
        'deepseek' => [
            'api_key'  => env('DEEPSEEK_API_KEY'),
            'model'    => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_uri' => env('DEEPSEEK_BASE_URI', 'https://api.deepseek.com'),
        ],
        'llama' => [
            'api_key'  => env('LLAMA_API_KEY'),
            'model'    => env('LLAMA_MODEL', 'llama3.1'),
            'base_uri' => env('LLAMA_BASE_URI', 'http://127.0.0.1:11434'),
        ],
        'mistral' => [
            'api_key'  => env('MISTRAL_API_KEY'),
            'model'    => env('MISTRAL_MODEL', 'mistral-large-latest'),
            'base_uri' => env('MISTRAL_BASE_URI', 'https://api.mistral.ai'),
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
        'model'      => env('AI_EMBEDDING_MODEL'),
        'api_key'    => env('AI_EMBEDDING_API_KEY'),
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
    | Vector Store Connection (Qdrant)
    |--------------------------------------------------------------------------
    | Connection details for the external vector DB. Used when vector_store is
    | 'qdrant'. Env-driven so config fallback is complete; the DB-backed
    | IntegrationSettings UI can override per-organization.
    */
    'vector' => [
        'host'       => env('QDRANT_HOST', 'http://127.0.0.1:6333'),
        'api_key'    => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'ruangasn'),
    ],

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
