<?php

namespace AgenticMorf\LaravelAICognee\Storage;

use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Storage\DatabaseConversationStore;
use AgenticMorf\LaravelAICognee\Jobs\PipeConversationTranscriptToCogneeJob;

class DatabaseConversationStoreWithCogneePipe implements ConversationStore
{
    public function __construct(
        protected DatabaseConversationStore $store
    ) {}

    public function latestConversationId(string|int $userId): ?string
    {
        return $this->store->latestConversationId($userId);
    }

    public function storeConversation(string|int|null $userId, string $title): string
    {
        return $this->store->storeConversation($userId, $title);
    }

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        return $this->store->storeUserMessage($conversationId, $userId, $prompt);
    }

    public function storeAssistantMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt, AgentResponse $response): string
    {
        $messageId = $this->store->storeAssistantMessage($conversationId, $userId, $prompt, $response);

        if (config('laravel-ai-cognee.memory.enabled', true)) {
            dispatch(new PipeConversationTranscriptToCogneeJob($conversationId))->afterResponse();
        }

        return $messageId;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        return $this->store->getLatestConversationMessages($conversationId, $limit);
    }
}
