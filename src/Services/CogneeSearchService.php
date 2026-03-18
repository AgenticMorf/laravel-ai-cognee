<?php

namespace AgenticMorf\LaravelAICognee\Services;

use Illuminate\Support\Facades\Log;

class CogneeSearchService
{
    public function __construct(
        protected CogneeClient $client
    ) {}

    /**
     * Search Cognee datasets.
     *
     * @param  array<int>  $excludeDocumentIds  Document IDs to exclude (used when documentIdResolver maps cognee_id to document IDs)
     * @param  array<string>|null  $datasetIds  Dataset UUIDs to search
     * @param  array<string>|null  $datasetNames  Dataset names to search (used if datasetIds empty)
     * @param  callable(string $cogneeId): int|null|null  $documentIdResolver  Optional: map cognee_id to app document ID for exclusion filtering
     * @param  int|null  $timeout  Override timeout in seconds (e.g. for memory search across many datasets)
     * @return array<int, array{document_id: int|null, content: string, source: string, cognee_id?: string}>
     */
    public function search(
        string $query,
        array $excludeDocumentIds = [],
        int $topK = 20,
        ?array $datasetIds = null,
        ?array $datasetNames = null,
        ?callable $documentIdResolver = null,
        ?int $timeout = null
    ): array {
        $rawResults = $this->client->search($query, $topK, $datasetIds, $datasetNames, $timeout);

        $results = [];
        foreach ($rawResults as $item) {
            $cogneeId = $this->extractCogneeId($item);
            $documentId = $documentIdResolver && $cogneeId ? $documentIdResolver($cogneeId) : null;

            if (! empty($excludeDocumentIds) && $documentId !== null && in_array($documentId, $excludeDocumentIds, true)) {
                continue;
            }

            $content = $this->extractContent($item);

            $row = [
                'document_id' => $documentId,
                'content' => $content,
                'source' => 'cognee',
            ];
            if ($cogneeId) {
                $row['cognee_id'] = $cogneeId;
            }
            $results[] = $row;
        }

        if (config('laravel-ai-cognee.debug_search_response', false) && $results !== []) {
            Log::debug('CogneeSearchService extracted content', [
                'query' => $query,
                'result_count' => count($results),
                'first_content_preview' => isset($results[0]['content']) ? substr($results[0]['content'], 0, 200) : null,
            ]);
        }

        return $results;
    }

    /**
     * Extract content from a Cognee search result item.
     * Handles: top-level, search_result (object/array), context_result, text_result.
     */
    protected function extractContent(array $item): string
    {
        $content = $item['content'] ?? $item['text'] ?? null;

        if ($content !== null && $content !== '') {
            return $content;
        }

        $sr = $item['search_result'] ?? null;
        if (is_array($sr)) {
            if (isset($sr['text']) || isset($sr['content'])) {
                return $sr['text'] ?? $sr['content'] ?? '';
            }
            if (array_is_list($sr) && isset($sr[0])) {
                $first = $sr[0];

                return is_array($first) ? ($first['text'] ?? $first['content'] ?? '') : (string) $first;
            }
        }

        $context = $item['context_result'] ?? null;
        if (is_array($context)) {
            $parts = [];
            foreach ($context as $chunk) {
                $t = is_array($chunk) ? ($chunk['text'] ?? $chunk['content'] ?? '') : (string) $chunk;
                if ($t !== '') {
                    $parts[] = $t;
                }
            }
            if ($parts !== []) {
                return implode("\n\n", $parts);
            }
        }

        $text = $item['text_result'] ?? null;
        if (is_string($text) && $text !== '') {
            return $text;
        }
        if (is_array($text) && isset($text[0])) {
            $first = $text[0];

            return is_array($first) ? ($first['text'] ?? $first['content'] ?? '') : (string) $first;
        }

        return '';
    }

    protected function extractCogneeId(array $item): ?string
    {
        $from = $item['data_id'] ?? $item['id'] ?? null;
        if ($from !== null) {
            return $from;
        }
        $sr = $item['search_result'] ?? null;
        if (is_array($sr)) {
            $id = $sr['data_id'] ?? $sr['id'] ?? null;
            if ($id !== null) {
                return $id;
            }
            if (array_is_list($sr) && isset($sr[0]) && is_array($sr[0])) {
                return $sr[0]['data_id'] ?? $sr[0]['id'] ?? null;
            }
        }

        return null;
    }
}
