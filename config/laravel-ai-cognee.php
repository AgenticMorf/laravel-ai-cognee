<?php

return [
    'url' => env('EYEJAY_COGNEE_URL', 'http://cognee:8000'),
    'api_token' => env('COGNEE_API_TOKEN'),
    'timeout' => (int) env('EYEJAY_COGNEE_TIMEOUT', 60),
    'datasets' => [
        // Closure: (?string $conversationId, ?int $userId) => string[]
        // App configures this to return dataset names/IDs for RAG and conversation memory
        'resolver' => null,
    ],
    'debug_search_response' => env('COGNEE_DEBUG_SEARCH', false),
    'memory' => [
        'enabled' => env('COGNEE_MEMORY_ENABLED', true),
        'top_k' => (int) env('COGNEE_MEMORY_TOP_K', 5),
        'search_timeout' => (int) env('COGNEE_MEMORY_SEARCH_TIMEOUT', 120),
    ],
];
