# Persistent Neuron chat history

## Purpose

NeuronAi uses Neuron AI's public chat-history extension point instead of
reimplementing message memory. `DatabaseNeuronChatHistory` extends the embedded
Neuron `AbstractChatHistory` class. Neuron therefore remains responsible for:

- user, assistant, tool-call and tool-result message objects;
- content-block serialization and deserialization;
- token accounting;
- context-window trimming;
- the order in which messages are added during the agent loop.

The BASE3 integration is responsible only for persistence, conversation scope
and concurrent-access protection.

No file below `src/Vendor` is modified.

## Conversation scope

The Chatbot browser creates a stable `conversation_id` and stores it in
`localStorage` under the configured chatbot identity. Every REST and SSE turn
transmits that ID.

The browser cannot choose the conversation owner. Before a turn is executed,
`ChatbotConversationContextFactory` replaces any submitted owner value with a
server-generated SHA-256 key based on:

1. the authenticated BASE3 user ID; or
2. the current BASE3 session ID for anonymous users.

The agent execution context contains:

- `conversation_id`;
- `conversation_owner_key`;
- `chatbot_config_group`;
- `chatbot_config_name`.

`NeuronConversationKeyFactory` hashes those values together with the runtime ID.
A conversation is therefore isolated by user/session, chatbot instance and
runtime.

## Database table

The repository creates `base3_neuronai_chathistory` lazily through
`Base3\Database\Api\IDatabase`.

Each conversation is stored as one Neuron-native serialized history document:

- `conversation_key`: unique technical SHA-256 key;
- `conversation_id`: browser-visible thread identifier;
- `owner_key`: server-generated user/session hash;
- `config_group` and `config_name`: chatbot instance identity;
- `runtime_id`: currently `neuronai`;
- `messages`: serialized Neuron message array;
- `message_count` and `version`: diagnostics and optimistic concurrency;
- creation, update and last-access timestamps.

The one-row-per-conversation model intentionally follows Neuron's own
`SQLChatHistory` design. It avoids duplicating Neuron's internal message model in
BASE3 tables and keeps upgrades localized to the public history adapter.

## Locking and concurrency

`NeuronChatHistoryFactory` acquires a short-lived StateStore lock before loading
a conversation. The lock key is:

```text
locks.neuronai.chathistory.<conversation_key>
```

The lock is released in a `finally` block after the agent run. A random token
prevents an expired and subsequently reacquired lock from being deleted by an
older request.

The database row also has an optimistic `version`. Every write updates only the
expected version. A conflicting write fails instead of silently losing a turn.

## Turn-level persistence

Neuron calls `AbstractChatHistory::setMessages()` after every message addition.
Persisting directly from that hook would write the user message before the
provider has returned an assistant response. A failed or cancelled provider call
would then leave an invalid `user, user` sequence for the next request.

`DatabaseNeuronChatHistory` therefore buffers Neuron's message changes during
one execution. `NeuronAgentExecutionService` commits the complete history only
after the workflow has produced a non-empty final assistant message. Failed and
cancelled runs discard the buffer while retaining the previously committed
conversation.

Histories written by older plugin versions may already end in a partial user,
tool-call or tool-result message. `NeuronChatHistoryRepository` removes such an
incomplete tail when loading and persists the repaired complete prefix before a
new turn starts.

The browser owner key is resolved before the SSE endpoint closes the PHP
session. NeuronAi never reopens the session during the long-running LLM request.

## New conversations

The existing Chatbot "Start new chat" action creates and persists a new
`conversation_id`, then reloads the widget. The previous history is retained for
a later thread-list implementation. The new conversation starts with an empty
Neuron history.

Conversation listing, titles, deletion and retention policies are deliberately
outside this first memory step.

## Upgrade checks

During every Neuron AI update, verify the public behavior of:

- `AbstractChatHistory::__construct()`;
- `AbstractChatHistory::deserializeMessages()`;
- `AbstractChatHistory::setMessages()`;
- `AbstractChatHistory::clear()`;
- `AgentInterface::setChatHistory()`;
- message `JsonSerializable` output;
- tool-call and tool-result history serialization.

Run the Neuron smoke test and the chat-history tests after rebuilding the
embedded runtime. Do not patch generated vendor files to retain compatibility.
