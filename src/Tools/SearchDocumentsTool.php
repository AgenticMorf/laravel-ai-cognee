<?php

namespace AgenticMorf\LaravelAICognee\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use AgenticMorf\LaravelAICognee\Services\CogneeSearchService;

class SearchDocumentsTool implements Tool
{
    public function __construct(
        protected CogneeSearchService $search
    ) {}

    public function description(): string
    {
        return 'Search the user\'s documents and bases for relevant content. Use when the user asks about documents, articles, or content they have uploaded.';
    }

    public function handle(Request $request): string
    {
        if (! config('fluxui-chat.rag.enabled', false)) {
            return 'Document search is disabled.';
        }

        $query = $request['query'] ?? '';
        if ($query === '') {
            return 'No search query provided.';
        }

        $resolver = config('laravel-ai-cognee.datasets.resolver');
        $context = $this->resolveConversationContext();
        $conversationId = $context?->conversationId ?? null;
        $userId = $context?->user?->getAuthIdentifier();

        $datasets = $resolver ? $resolver($conversationId, $userId) : [];

        $datasetIds = [];
        $datasetNames = [];
        foreach ($datasets ?? [] as $ds) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $ds)) {
                $datasetIds[] = $ds;
            } else {
                $datasetNames[] = $ds;
            }
        }

        $ids = $datasetIds !== [] ? $datasetIds : null;
        $names = $datasetNames !== [] ? $datasetNames : null;

        $topK = (int) ($request['top_k'] ?? config('fluxui-chat.rag.top_k', 10));
        $topK = min(max($topK, 1), 20);

        try {
            $results = $this->search->search($query, [], $topK, $ids, $names);
        } catch (\Throwable $e) {
            Log::warning('SearchDocumentsTool: Cognee search failed', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);

            return 'No relevant document content found.';
        }

        $content = collect($results)
            ->pluck('content')
            ->filter()
            ->implode("\n\n");

        return $content !== '' ? $content : 'No relevant document content found.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search query (e.g. "insurance claims process", "article about X")')
                ->required(),
            'top_k' => $schema->integer()
                ->description('Maximum number of document chunks to return (default: 10)')
                ->min(1)
                ->max(20),
        ];
    }

    protected function resolveConversationContext(): ?object
    {
        $contextClass = config('fluxui-chat.conversation_context_class');
        if ($contextClass === null || ! class_exists($contextClass)) {
            return null;
        }

        if (! app()->bound($contextClass)) {
            return null;
        }

        $context = app($contextClass);
        if (! is_object($context) || ! property_exists($context, 'conversationId')) {
            return null;
        }

        return $context;
    }
}
