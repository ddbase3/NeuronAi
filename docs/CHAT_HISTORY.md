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
and optimistic concurrent-write protection.

No file below `src/Vendor` is modified.

## Conversation scope

The Chatbot browser creates a stable `conversation_id` and stores it in
`localStorage` under the configured chatbot identity. Every REST and SSE turn
transmits that ID.

The browser cannot choose the conversation owner. `NeuronConversationOwnerResolver`
creates the server-owned SHA-256 owner key from:

1. the authenticated BASE3 user ID; or
2. the current BASE3 session ID for anonymous users.

The agent execution context supplies the conversation and chatbot identity:

- `conversation_id`;
- `chatbot_config_group`;
- `chatbot_config_name`.

`NeuronConversationKeyFactory` combines those values with the resolved owner key
and the runtime ID. A conversation is therefore isolated by user/session, chatbot
instance and runtime.

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

## Concurrency

The canonical database row owns conversation concurrency. It has an optimistic
`version`, and every history write updates only the expected version. A
conflicting write fails instead of silently losing a turn.

NeuronAi deliberately does not keep a second StateStore conversation lock. This
keeps suspend/resume runs and normal sequential turns on the same persistence
boundary and avoids stale locks blocking a conversation after a paused tool call.

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

Suggestion requests use the normal runtime with `mode=suggestions`. They load the
active Neuron history as read-only context, expose no executable tools or MCP
configuration, and discard the temporary suggestion turn after the model
response. The literal prompt `Generate suggestions.` and the generated result
therefore never become conversation messages.

The server-owned owner key is resolved before the long-running Neuron request begins.
The browser never supplies or selects the owner identity.

## New conversations

The Chatbot "Start new chat" action creates a new `conversation_id`. The previous
history remains available in the conversation list and the new conversation starts
with an empty Neuron history.

The same table also owns conversation titles, activation timestamps and opening-message
metadata. `NeuronAgentConversationService` exposes this one store through
`IAgentConversationRuntimeService`, so chat listing, activation, rename, delete and
restore do not create a second conversation persistence path.

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
