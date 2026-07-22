# NeuronAi

NeuronAi is a BASE3 agent-runtime plugin for the Neuron AI framework. It runs
under ILIAS and standalone BASE3 without changing the host Composer project and
without registering a second runtime autoloader.

Neuron AI `3.15.26` and its runtime dependencies are delivered below the
private `NeuronAi\Vendor` namespace. Host versions of Guzzle, PSR packages or
Inspector therefore cannot collide with the embedded runtime.

## Responsibilities

NeuronAi owns:

- the pinned and isolated Neuron AI runtime;
- the named `neuronai` runtime implementation;
- Neuron-specific instructions, tool-run and MCP settings;
- provider and agent factories;
- translation of Neuron streaming events into AssistantFoundation events;
- Neuron-native persistent chat history through a BASE3 database adapter.

NeuronAi does not own:

- LLM or connection administration;
- credentials;
- Chatbot HTTP, REST or SSE transport;
- runtime routing and shared form composition;
- MissionBay flow construction.

Shared concrete runtime infrastructure lives in `AssistantRuntime`. Shared
contracts and DTOs live in `AssistantFoundation`.

## Agent configuration

A Neuron agent selects one existing configured LLM through the generic `llm`
field. The selected LLM references its connection. At execution time the
runtime-neutral `IAiModelConfigurationProvider` resolves:

- driver;
- model;
- endpoint;
- provider options;
- the credential referenced by the connection.

Neuron agent records therefore contain no duplicate provider, endpoint, model
or credential configuration.

Additional Neuron settings are:

- `neuron_instructions`;
- `neuron_max_tool_runs`;
- `neuron_mcp`.

The current adapter supports configured `openai-chat`,
`openai-compatible-chat` and `mistral-chat` services.

## Dependency injection

All NeuronAi defaults use `IContainer::NOOVERWRITE`:

- `INeuronProviderFactory`;
- `INeuronAgentFactory`;
- `INeuronChatHistoryFactory`;
- `NeuronExecutionEventMapper`;
- `NeuronAgentExecutionService`;
- `NeuronAgentConfigFormService`.

The provider factory depends only on
`AssistantFoundation\Api\IAiModelConfigurationProvider`. A host can replace
that provider without changing NeuronAi. See `docs/DI.md`.

## Persistent conversation memory

Chatbot turns provide a stable conversation ID and a server-owned user/session
scope. NeuronAi attaches a database-backed implementation of Neuron's public
`AbstractChatHistory` to the agent. Neuron remains responsible for message
serialization, tool messages, token accounting and context-window trimming.

Histories are stored in `base3_neuronai_chathistory`. The StateStore is used
only for a short-lived per-conversation lock. A new-chat action creates a new
conversation ID; the previous history remains available for a later thread-list
implementation.

See `docs/CHAT_HISTORY.md` for the exact scope, schema and upgrade contract.

## Existing BASE3 tools through MCP

The pilot integration can reach an MCP server through non-secret agent
configuration:

```php
[
    'neuron_mcp' => [
        'url' => 'https://example.invalid/mcp',
        'only' => ['read_report'],
        'exclude' => []
    ]
]
```

Credentials must be supplied by the MCP endpoint or a future host-specific MCP
client adapter; they are not stored in agent JSON. MCP is disabled for prompt
suggestion executions.

## Embedded runtime maintenance

There are no hand-edited files in `src/Vendor`. The private runtime is rebuilt
from the pinned Composer lock and PHP-Scoper configuration. Local integration
and transformations are documented in:

- `docs/ARCHITECTURE.md`;
- `docs/DI.md`;
- `docs/DEPENDENCY_ISOLATION.md`;
- `docs/CHAT_HISTORY.md`;
- `docs/UPSTREAM_CHANGES.md`;
- `docs/UPGRADE.md`;
- `THIRD_PARTY/manifest.json`.
