<?php

namespace AgenticMorf\LaravelAICognee\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use AgenticMorf\LaravelAICognee\Events\CogneeContentAdded;
use AgenticMorf\LaravelAICognee\Services\CogneeClient;

class CogneeAddContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $metadata  Optional metadata; on success, CogneeContentAdded event is dispatched for app to handle (e.g. update Document::cognee_id)
     */
    public function __construct(
        public string $content,
        public string $datasetIdentifier,
        public bool $force = false,
        public ?string $existingCogneeId = null,
        public array $metadata = []
    ) {
        $this->onConnection('redis')->onQueue('cognee');
    }

    public function handle(CogneeClient $client): void
    {
        if ($this->force && $this->existingCogneeId) {
            $datasetId = $client->resolveDatasetId($this->datasetIdentifier);
            $client->delete($datasetId, $this->existingCogneeId);
        }

        $cogneeId = $client->add($this->datasetIdentifier, $this->content);
        if ($cogneeId !== null) {
            $client->cognify([$this->datasetIdentifier], runInBackground: true);
            event(new CogneeContentAdded($cogneeId, $this->metadata));
        }
    }
}
