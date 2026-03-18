<?php

namespace AgenticMorf\LaravelAICognee\Providers;

use AgenticMorf\FluxUIChat\Contracts\MemoryContextProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use AgenticMorf\LaravelAICognee\Services\CogneeSearchService;

class CogneeMemoryContextProvider implements MemoryContextProvider
{
    public function __construct(
        protected CogneeSearchService $search
    ) {}

    public function getContext(?string $conversationId, string $message, int $topK = 5, ?Authenticatable $user = null): string
    {
        if (! config('laravel-ai-cognee.memory.enabled', true)) {
            return '';
        }

        $topK = config('laravel-ai-cognee.memory.top_k', $topK);
        $datasetNames = $this->resolveDatasetNames($conversationId, $user);

        if ($datasetNames === []) {
            return '';
        }

        if (config('laravel-ai-cognee.debug_search_response', false)) {
            Log::debug('CogneeMemoryContextProvider getContext', [
                'message' => $message,
                'datasets' => $datasetNames,
                'conversation_id' => $conversationId,
            ]);
        }

        $results = $this->search->search(
            $message,
            [],
            $topK,
            null,
            $datasetNames
        );

        $context = collect($results)
            ->pluck('content')
            ->filter()
            ->implode("\n\n");

        if (config('laravel-ai-cognee.debug_search_response', false) && $context !== '') {
            Log::debug('CogneeMemoryContextProvider context', [
                'context_length' => strlen($context),
                'context_preview' => substr($context, 0, 300),
            ]);
        }

        return $context;
    }

    /**
     * Resolve dataset names to search.
     * Multi-user: only current conversation dataset (no cross-conversation, no user dataset).
     * Single-user: current conversation + user dataset + all accessible conversation IDs.
     *
     * @return array<string>
     */
    protected function resolveDatasetNames(?string $conversationId, ?Authenticatable $user): array
    {
        $userId = $user !== null ? (string) $user->getAuthIdentifier() : null;

        if ($conversationId !== null && $conversationId !== '' && $userId !== null) {
            if ($this->isMultiUser($conversationId, $userId)) {
                return [$conversationId];
            }
        }

        if ($user !== null) {
            $conversationIds = $this->getAccessibleConversationIds((string) $user->getAuthIdentifier());
            $userDataset = 'user_'.(string) $user->getAuthIdentifier();

            return array_values(array_unique([...$conversationIds, $userDataset]));
        }

        return $conversationId !== null && $conversationId !== '' ? [$conversationId] : [];
    }

    /**
     * Conversation is multi-user if it has shares or the current user is not the owner.
     */
    protected function isMultiUser(string $conversationId, string $userId): bool
    {
        $hasShares = DB::table('conversation_shares')
            ->where('conversation_id', $conversationId)
            ->exists();

        if ($hasShares) {
            return true;
        }

        $ownerId = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->value('user_id');

        return $ownerId !== $userId;
    }

    /**
     * Get all conversation IDs accessible to the user (own + shared).
     *
     * @return array<string>
     */
    protected function getAccessibleConversationIds(string $userId): array
    {
        if (config('database.default') === 'pgsql') {
            $rows = DB::select('SELECT * FROM get_accessible_conversation_ids(?) AS t(id)', [$userId]);

            return array_map(fn ($r) => $r->id, $rows);
        }

        $own = DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->pluck('id')
            ->all();

        $shared = DB::table('conversation_shares')
            ->where('shareable_type', 'App\Models\User')
            ->where('shareable_id', $userId)
            ->pluck('conversation_id')
            ->all();

        return array_values(array_unique(array_merge($own, $shared)));
    }
}
