<?php

namespace AgenticMorf\LaravelAICognee\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class ConversationDatasetResolver
{
    /**
     * Resolve dataset names to search for conversation memory.
     * Multi-user: only current conversation dataset.
     * Single-user: current conversation + user dataset + all accessible conversation IDs.
     *
     * @return array<string>
     */
    public function resolve(?string $conversationId, ?Authenticatable $user): array
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
