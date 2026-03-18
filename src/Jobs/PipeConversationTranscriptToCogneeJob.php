<?php

namespace AgenticMorf\LaravelAICognee\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use AgenticMorf\LaravelAICognee\ConversationTranscript;
use AgenticMorf\LaravelAICognee\Services\CogneeClient;

class PipeConversationTranscriptToCogneeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public string $conversationId
    ) {
        $this->onConnection('sync')->onQueue('default');
    }

    public function handle(CogneeClient $client, ConversationTranscript $transcript): void
    {
        $content = $transcript->transcript($this->conversationId);
        if ($content === '') {
            return;
        }

        $datasetId = $client->createDataset($this->conversationId)
            ?? $client->resolveDatasetId($this->conversationId);

        $existing = $client->getDatasetData($datasetId);
        foreach ($existing as $item) {
            $dataId = $item['id'] ?? $item['Id'] ?? $item['data_id'] ?? null;
            if ($dataId !== null) {
                $client->delete($datasetId, $dataId);
            }
        }

        $client->add($datasetId, $content);
        $client->cognify([$datasetId], runInBackground: true, useIds: true);

        $ownerContent = $transcript->ownerStatements($this->conversationId);
        if ($ownerContent !== '') {
            $this->writeUserDataset($client, $transcript);
        }
    }

    protected function writeUserDataset(CogneeClient $client, ConversationTranscript $transcript): void
    {
        $ownerId = DB::table('agent_conversations')
            ->where('id', $this->conversationId)
            ->value('user_id');

        if ($ownerId === null) {
            return;
        }

        $conversationIds = DB::table('agent_conversations')
            ->where('user_id', $ownerId)
            ->pluck('id');

        $parts = [];
        foreach ($conversationIds as $convId) {
            $statements = $transcript->ownerStatements($convId);
            if ($statements !== '') {
                $parts[] = "Conversation {$convId}:\n\n{$statements}";
            }
        }

        $ownerContent = implode("\n\n---\n\n", $parts);
        if ($ownerContent === '') {
            return;
        }

        $userDatasetName = 'user_'.$ownerId;
        $userDatasetId = $client->createDataset($userDatasetName)
            ?? $client->resolveDatasetId($userDatasetName);

        $existing = $client->getDatasetData($userDatasetId);
        foreach ($existing as $item) {
            $dataId = $item['id'] ?? $item['Id'] ?? $item['data_id'] ?? null;
            if ($dataId !== null) {
                $client->delete($userDatasetId, $dataId);
            }
        }

        $client->add($userDatasetId, $ownerContent);
        $client->cognify([$userDatasetId], runInBackground: true, useIds: true);
    }
}
