<?php

namespace AgenticMorf\LaravelAICognee;

use Illuminate\Support\Facades\DB;

class ConversationTranscript
{
    /**
     * Build a formatted transcript of the conversation for Cognee.
     */
    public function transcript(string $conversationId): string
    {
        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get(['role', 'content']);

        $turns = [];
        foreach ($messages as $message) {
            $label = $message->role === 'user' ? 'User' : 'Assistant';
            $turns[] = "{$label}: {$message->content}";
        }

        return implode("\n\n", $turns);
    }

    /**
     * Build a formatted transcript of only the owner's user messages for Cognee user dataset.
     * Avoids PII cross-contamination in multi-user chats.
     */
    public function ownerStatements(string $conversationId): string
    {
        $ownerId = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->value('user_id');

        if ($ownerId === null) {
            return '';
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $ownerId)
            ->where('role', 'user')
            ->orderBy('created_at')
            ->get(['content']);

        return collect($messages)
            ->pluck('content')
            ->map(fn (string $c) => "User: {$c}")
            ->implode("\n\n");
    }
}
