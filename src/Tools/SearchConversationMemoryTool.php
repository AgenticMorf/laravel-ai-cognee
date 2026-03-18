<?php

namespace AgenticMorf\LaravelAICognee\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use AgenticMorf\LaravelAICognee\Services\CogneeSearchService;
use AgenticMorf\LaravelAICognee\Services\ConversationDatasetResolver;

class SearchConversationMemoryTool implements Tool
{
    public function __construct(
        protected CogneeSearchService $search,
        protected ConversationDatasetResolver $datasetResolver
    ) {}

    public function description(): string
    {
        return 'Search across the user\'s past conversations for relevant facts they have shared (e.g. name, preferences, context). Use when you need to recall information the user told you in previous chats.';
    }

    public function handle(Request $request): string
    {
        if (! config('laravel-ai-cognee.memory.enabled', true)) {
            return 'Conversation memory search is disabled.';
        }

        $query = $request['query'] ?? '';
        if ($query === '') {
            return 'No search query provided.';
        }

        $context = $this->resolveConversationContext();
        if ($context === null) {
            return 'No conversation context available.';
        }

        $datasetNames = $this->datasetResolver->resolve($context->conversationId, $context->user);
        if ($datasetNames === []) {
            return 'No datasets to search.';
        }

        $topK = (int) ($request['top_k'] ?? config('laravel-ai-cognee.memory.top_k', 5));
        $topK = min(max($topK, 1), 20);
        $searchTimeout = (int) config('laravel-ai-cognee.memory.search_timeout', 120);

        try {
            $results = $this->search->search($query, [], $topK, null, $datasetNames, null, $searchTimeout);
        } catch (\Throwable $e) {
            Log::warning('SearchConversationMemoryTool: Cognee search failed', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);

            return 'No relevant conversation memory found.';
        }

        $content = collect($results)
            ->pluck('content')
            ->filter()
            ->implode("\n\n");

        return $content !== '' ? $content : 'No relevant conversation memory found.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search query (e.g. "What is my name?", "user preferences")')
                ->required(),
            'top_k' => $schema->integer()
                ->description('Maximum number of memory chunks to return (default: 5)')
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
