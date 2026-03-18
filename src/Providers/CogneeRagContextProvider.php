<?php

namespace AgenticMorf\LaravelAICognee\Providers;

use AgenticMorf\FluxUIChat\Contracts\RagContextProvider;
use AgenticMorf\LaravelAICognee\Services\CogneeSearchService;

class CogneeRagContextProvider implements RagContextProvider
{
    public function __construct(
        protected CogneeSearchService $search
    ) {}

    public function getContext(string $message, int $topK = 10): string
    {
        $conversationId = request()->route('conversationId') ?? null;
        $userId = auth()->id();

        $resolver = config('laravel-ai-cognee.datasets.resolver');
        $datasets = $resolver ? $resolver($conversationId, $userId) : [];

        $datasetIds = [];
        $datasetNames = [];
        foreach ($datasets ?? [] as $ds) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ds)) {
                $datasetIds[] = $ds;
            } else {
                $datasetNames[] = $ds;
            }
        }

        $ids = ! empty($datasetIds) ? $datasetIds : null;
        $names = ! empty($datasetNames) ? $datasetNames : null;

        $results = $this->search->search($message, [], $topK, $ids, $names);

        return collect($results)->pluck('content')->implode("\n\n");
    }
}
