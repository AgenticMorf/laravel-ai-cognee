<?php

namespace AgenticMorf\LaravelAICognee\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CogneeClient
{
    public function __construct(
        protected string $url,
        protected ?string $apiToken,
        protected int $timeout = 60
    ) {}

    protected function headers(): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiToken) {
            $headers['Authorization'] = 'Bearer '.$this->apiToken;
        }

        return $headers;
    }

    /**
     * Create a new dataset or return existing dataset with the same name.
     * Returns the dataset UUID.
     */
    public function createDataset(string $name): ?string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->post($this->url.'/api/v1/datasets', [
                'name' => $name,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $id = $data['id'] ?? $data['Id'] ?? null;

        return $id;
    }

    /**
     * Add content to a dataset.
     * Returns the cognee data_id (or null on failure).
     * Cognee add API expects multipart/form-data with files; content is sent as a text file.
     * $datasetIdentifier must be a dataset UUID (from createDataset) or dataset name. Must not be empty.
     */
    public function add(string $datasetIdentifier, string $content, ?string $dataId = null): ?string
    {
        $datasetIdentifier = trim($datasetIdentifier);
        if ($datasetIdentifier === '') {
            Log::warning('CogneeClient::add skipped: empty dataset identifier');

            return null;
        }

        $body = is_string($content) ? $content : json_encode($content);

        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $datasetIdentifier);
        $params = $isUuid ? ['datasetId' => $datasetIdentifier] : ['datasetName' => $datasetIdentifier];

        $filename = 'content_'.substr(md5($body), 0, 8).'.txt';
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->attach('data', $body, $filename)
            ->post($this->url.'/api/v1/add', $params);

        if (! $response->successful()) {
            Log::warning('CogneeClient::add failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $cogneeId = $data['data_id'] ?? $data['id'] ?? $data['Id'] ?? null;
        if ($cogneeId === null && isset($data['data_ingestion_info'][0]['data_id'])) {
            $cogneeId = $data['data_ingestion_info'][0]['data_id'];
        }

        return $cogneeId;
    }

    /**
     * Cognify: transform raw data in a dataset into structured knowledge graph.
     *
     * @param  array<string>  $datasets  Dataset names or UUIDs
     * @param  bool  $runInBackground  If true, returns immediately; Cognee processes async.
     * @param  bool  $useIds  If true, send as dataset_ids (UUIDs); else as datasets (names).
     */
    public function cognify(array $datasets, bool $runInBackground = true, bool $useIds = false): bool
    {
        $filtered = array_values(array_filter($datasets, fn (string $d) => trim($d) !== ''));
        if ($filtered === []) {
            Log::warning('CogneeClient::cognify skipped: no valid dataset identifiers');

            return false;
        }

        $payload = [
            'run_in_background' => $runInBackground,
        ];
        $payload[$useIds ? 'dataset_ids' : 'datasets'] = $filtered;

        $timeout = $runInBackground ? $this->timeout : 180; // Sync cognify can take 2+ min for embedding pipeline
        $response = Http::timeout($timeout)
            ->withHeaders($this->headers())
            ->post($this->url.'/api/v1/cognify', $payload);

        return $response->successful();
    }

    /**
     * Delete a data item from a dataset.
     */
    public function delete(string $datasetId, string $dataId): void
    {
        Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->delete($this->url.'/api/v1/datasets/'.$datasetId.'/data/'.$dataId);
    }

    /**
     * Resolve dataset UUID from a name or stored id.
     */
    public function resolveDatasetId(string $datasetIdentifier): string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->get($this->url.'/api/v1/datasets');

        if (! $response->successful()) {
            throw new \RuntimeException("Could not resolve Cognee dataset: {$datasetIdentifier}");
        }

        $datasets = $response->json();
        foreach ($datasets as $ds) {
            $dsName = $ds['name'] ?? $ds['Name'] ?? null;
            $dsId = $ds['id'] ?? $ds['Id'] ?? null;
            if ($dsName === $datasetIdentifier || $dsId === $datasetIdentifier) {
                return $dsId ?? $datasetIdentifier;
            }
        }

        throw new \RuntimeException("Cognee dataset not found: {$datasetIdentifier}");
    }

    /**
     * Get all data items in a dataset.
     *
     * @return array<int, array{id: string, name?: string, created_at?: string, createdAt?: string, updated_at?: string, updatedAt?: string}>
     */
    public function getDatasetData(string $datasetId): array
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->get($this->url.'/api/v1/datasets/'.$datasetId.'/data');

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * Get raw content for a specific data item.
     */
    public function getRawData(string $datasetId, string $dataId): string
    {
        $response = Http::timeout($this->timeout)
            ->withHeaders($this->headers())
            ->get($this->url.'/api/v1/datasets/'.$datasetId.'/data/'.$dataId.'/raw');

        if (! $response->successful()) {
            return '';
        }

        return $response->body();
    }

    /**
     * Search across datasets.
     *
     * @param  array<string>|null  $datasetIds  Dataset UUIDs
     * @param  array<string>|null  $datasetNames  Dataset names (used if datasetIds empty)
     * @param  int|null  $timeout  Override timeout in seconds (e.g. for memory search across many datasets)
     * @return array<int, array{data_id?: string, id?: string, content?: string, text?: string, search_result?: array}>
     */
    public function search(string $query, int $topK = 20, ?array $datasetIds = null, ?array $datasetNames = null, ?int $timeout = null): array
    {
        $payload = [
            'query' => $query,
            'search_type' => 'CHUNKS',
            'top_k' => $topK,
            'verbose' => true,
        ];

        if ($datasetIds !== null && $datasetIds !== []) {
            $payload['dataset_ids'] = $datasetIds;
        } elseif ($datasetNames !== null && $datasetNames !== []) {
            $payload['datasets'] = $datasetNames;
        }

        $timeoutSeconds = $timeout ?? $this->timeout;
        $response = Http::timeout($timeoutSeconds)
            ->withHeaders($this->headers())
            ->post($this->url.'/api/v1/search', $payload);

        if (! $response->successful()) {
            $body = $response->body();
            if ($response->status() === 404 && str_contains($body, 'NoDataError')) {
                return [];
            }
            throw new \RuntimeException(
                'Cognee search failed: HTTP '.$response->status().' '.$body
            );
        }

        $data = $response->json();
        $results = $data['results'] ?? $data['data'] ?? null;
        if ($results === null && is_array($data) && isset($data[0]) && array_is_list($data)) {
            $results = $data;
        }
        $results = $results ?? [];

        if (config('laravel-ai-cognee.debug_search_response', false) && $results !== []) {
            Log::debug('Cognee search raw response', [
                'query' => $query,
                'result_count' => count($results),
                'first_result_keys' => isset($results[0]) ? array_keys($results[0]) : [],
                'first_result' => $results[0] ?? null,
            ]);
        }

        return $results;
    }
}
