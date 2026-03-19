---
title: Usage
---

# Usage

## Conversation Store

The package replaces Laravel AI's default conversation store with `DatabaseConversationStoreWithCogneePipe`. After each assistant message, it dispatches `PipeConversationTranscriptToCogneeJob` to sync the conversation transcript to Cognee for semantic search.

## RAG and Memory (fluxui-chat)

When using agenticmorf/fluxui-chat, register the providers in config:

- `CogneeRagContextProvider` — RAG context from document search
- `CogneeMemoryContextProvider` — Memory context from conversation search

Configure `fluxui-chat.rag.context_provider` and `fluxui-chat.memory.context_provider` to use these classes.

## Tools

- **SearchDocumentsTool** — Search user documents and bases. Requires `datasets.resolver` and `fluxui-chat.rag.enabled`.
- **SearchConversationMemoryTool** — Search past conversations for user facts. Requires `memory.enabled` and `fluxui-chat.conversation_context_class`.

## Dataset Resolver

The resolver returns dataset names (strings) or UUIDs for Cognee search. Example:

```php
'datasets' => [
    'resolver' => fn (?string $conversationId, ?int $userId) => [
        $conversationId,
        'user_' . $userId,
    ],
],
```
