# Privacy and Data Processing in NeuronAi

> This document describes the data-processing behavior implemented by the NeuronAi BASE3 integration. It is a technical component document, not a legal privacy notice. The privacy behavior of the embedded third-party libraries themselves is not reproduced here. For third-party package details, use `THIRD_PARTY/manifest.json` and the corresponding upstream documentation.

## 1. Component scope

NeuronAi integrates the Neuron AI agent runtime with BASE3 runtime-neutral AI contracts. It creates provider instances, executes agent turns, maps streaming events, integrates shared context and tool profiles, supports optional MCP tools, and can persist Neuron-native conversation history through the BASE3 database abstraction.

The component therefore participates directly in processing prompts, model responses, conversation history, runtime context and tool data.

It does not own the general LLM credential store, chatbot browser transport, shared runtime registry, or the generic persistence backend for approval suspensions.

## 2. Third-party runtime boundary

The upstream Neuron AI runtime and its dependencies are third-party software embedded below the private `NeuronAi\\Vendor` namespace.

The exact package inventory, pinned versions, source references and declared licenses are recorded in:

- `THIRD_PARTY/manifest.json`
- `build/composer.lock`

This document does not attempt to restate the internal privacy, security or networking behavior of those packages. Their own documentation and source remain authoritative for library-internal behavior.

NeuronAi is responsible for how the BASE3 integration configures and invokes that runtime.

## 3. Main data categories processed by the integration

Depending on the selected runtime configuration and agent capabilities, NeuronAi can process:

- user prompts;
- assistant responses;
- persistent conversation messages;
- tool calls, tool arguments and tool results;
- conversation identifiers and titles;
- opening messages;
- server-derived conversation owner hashes;
- chatbot or configuration identifiers;
- runtime context contributed by a selected context profile;
- LLM model configuration resolved at runtime;
- LLM API credentials in process memory;
- external MCP configuration and MCP tool arguments or results;
- interaction and approval data for confirmable tools;
- provider usage metadata returned by the upstream runtime;
- errors, warnings and execution diagnostics propagated through runtime contracts.

Any of these fields can contain personal, confidential or otherwise sensitive information depending on the application using the agent.

## 4. LLM provider communication

A normal Neuron agent call sends model input through the configured LLM provider.

The current BASE3 adapter supports normalized drivers for OpenAI chat, OpenAI-compatible chat and Mistral chat. `NeuronProviderFactory` resolves the selected model through `IAiModelConfigurationProvider` and uses the resulting endpoint, model, options and API key to create the upstream provider object.

The exact provider protocol and payload construction are implemented by the embedded third-party runtime. Consult the pinned upstream Neuron AI documentation for those internals.

At the BASE3 integration boundary, assume that the provider may receive:

- current user prompt;
- relevant persistent conversation history;
- current system instructions;
- context-profile instruction blocks;
- tool descriptions made available to the model;
- tool results returned to the model during an agent run.

## 5. Provider endpoint boundary

`ConfiguredEndpointHttpClient` rewrites the provider's `chat/completions` request to the exact absolute endpoint from the normalized BASE3 model configuration.

The endpoint may use `http` or `https`. The integration does not force TLS. A deployment that sends personal or confidential data outside a trusted local network should therefore configure HTTPS and validate the security properties of the selected provider endpoint.

The endpoint is configuration data, not normal end-user input. It should only be editable by trusted administration paths because it determines where model requests are sent.

## 6. LLM credentials

NeuronAi does not persist a separate copy of the selected LLM API key in its agent settings or chat-history table.

The resolved API key is supplied by `IAiModelConfigurationProvider` and used in process memory when the provider is created. Credential storage, encryption, rotation, auditing and access control belong to the configured model and connection infrastructure outside this component.

A memory dump, exception handler, profiler or other host-level instrumentation with access to process memory can still potentially observe runtime credentials. This risk is not unique to NeuronAi and must be managed at the host boundary.

## 7. Persistent conversation storage

The built-in `neuronai-database` memory profile stores conversations in:

```text
base3_neuronai_chathistory
```

The table is owned by NeuronAi and stores one canonical row per scoped conversation.

Stored fields include:

- technical row ID;
- `conversation_key`;
- `conversation_id`;
- `owner_key`;
- `config_group`;
- `config_name`;
- `runtime_id`;
- conversation `title`;
- `title_source`;
- optional `opening_message`;
- serialized `messages`;
- `message_count`;
- optimistic `version`;
- `created_at`;
- `updated_at`;
- `last_accessed_at`.

The serialized `messages` field is the most privacy-sensitive field because it can contain complete user and assistant content and may also contain Neuron-native tool-related message structures.

## 8. Conversation owner identity

The conversation owner is resolved on the server.

For an authenticated user, NeuronAi derives the owner identity from the BASE3 user ID. For an anonymous user, it derives the owner identity from the active BASE3 session ID.

The stored `owner_key` is a SHA-256 hash of a namespaced identity such as `user:<id>` or `session:<id>`.

The raw user ID or session ID is not stored in the Neuron history table by this component.

The hash is pseudonymous, not necessarily anonymous. It can still represent a stable person or session and must therefore be treated as potentially personal data.

## 9. Conversation key isolation

`NeuronConversationKeyFactory` builds a second SHA-256 key from:

- runtime ID;
- owner key;
- configuration group;
- configuration name;
- conversation ID.

This prevents a browser-provided conversation ID alone from selecting another user's history. The server-owned owner key remains part of the persistence scope.

## 10. Session processing

NeuronAi does not create a separate application cookie.

For anonymous conversation ownership it can start and read the existing BASE3 session through `ISession`. The session identifier is used to derive the server-owned owner hash.

Session creation, cookie attributes, session storage and session retention are owned by the configured BASE3 session implementation, not by NeuronAi.

## 11. Conversation titles and opening messages

Conversation metadata can include a title, title source and optional opening message. These fields are stored in the same history row.

Titles and opening messages can contain personal or sensitive text if generated from or entered in a sensitive context. They should therefore receive the same access-control and retention treatment as the message history itself.

## 12. Visible and native history data

The runtime-neutral conversation service exposes visible user and assistant messages for conversation UI purposes.

The underlying Neuron history can contain additional upstream-native structures such as tool-call and tool-result messages. Those structures remain in the serialized history even when the conversation UI only returns normal user and assistant text.

For the exact serialized message model, consult the pinned upstream Neuron AI runtime.

## 13. Turn-level commit behavior

`DatabaseNeuronChatHistory` buffers changes during an agent execution.

A normal turn commits only after a non-empty final assistant response has been produced. Failed and cancelled runs discard the buffered history. Suggestion executions also discard the temporary turn.

This reduces accidental persistence of incomplete turns, but it does not change the external provider processing that already occurred during the failed, cancelled or suggestion request.

## 14. Concurrency metadata

The history table stores an integer `version` and uses optimistic version checks to prevent silent lost updates.

The current source does not maintain a separate NeuronAi conversation lock in the State Store. A conflicting write fails if another request changed the canonical row first.

The version and timestamps are technical metadata but can still reveal activity patterns for a conversation.

## 15. Conversation retention

NeuronAi provides explicit conversation deletion, which deletes the scoped history row.

The current component does not include an automatic age-based retention job for `base3_neuronai_chathistory`. Without an external retention policy, conversation rows can remain stored until a user or application explicitly deletes them or the database is otherwise maintained.

A production deployment should define:

- maximum conversation retention if required;
- behavior when a user account is deleted;
- behavior when anonymous sessions expire;
- backup retention;
- database replica retention;
- deletion verification where legally or contractually required.

## 16. Database backups and replicas

Deleting the live history row does not automatically erase copies already present in backups, replicas, snapshots or database audit systems.

Those systems are outside NeuronAi and require their own retention and restore procedures.

## 17. Dynamic context profiles

A selected `context_profile` is resolved again for each execution.

The resulting instruction blocks are appended to the current Neuron instructions and are not appended to persistent conversation history by NeuronAi.

This separation prevents changing runtime context from becoming stale chat memory. It does not prevent the current context from being sent to the configured LLM provider during that execution.

Context contributors must therefore avoid supplying data that the selected provider should not receive.

## 18. Context diagnostics

The NeuronAi integration carries context warnings and diagnostics in execution results. The documented context-profile integration uses diagnostics such as IDs, sources and lengths rather than copying the context content itself.

A consuming event sink, telemetry system or UI can still choose to store returned diagnostics. That storage is outside this component.

## 19. Direct BASE3 tools

NeuronAi can attach a runtime-neutral `IAgentToolSet` to an agent.

Tool definitions can expose capability names, descriptions and schemas to the model. When the model requests a tool, the Neuron adapter forwards the call through the shared tool-set boundary.

Tool arguments and results may contain personal or sensitive data. Their privacy handling depends on the concrete tool and the shared tool infrastructure.

NeuronAi does not create a separate private tool-result database.

## 20. Tool execution events

The execution service can emit events containing:

- tool name and label;
- tool call ID;
- tool arguments;
- iteration and call index;
- tool result;
- error information;
- execution metadata.

These event payloads can be sensitive. The `IAgentEventSink` is supplied by consuming runtime infrastructure, which determines whether the events are streamed, displayed, logged or persisted.

## 21. Approval-bound tool suspensions

For a confirmable tool, the server can persist an approval suspension through the shared `IAgentSuspensionRepository`.

NeuronAi requests a TTL of 900 seconds, or 15 minutes, for its suspensions.

The suspension can contain:

- the original user prompt;
- the exact pending tool call;
- tool arguments;
- already completed tool results;
- interaction request data;
- runtime metadata;
- a server-generated resume handle.

This is sensitive temporary execution state.

The actual storage backend, encryption properties and cleanup implementation belong to the shared suspension repository, not to a private NeuronAi table.

## 22. Approval resume behavior

On resume, the server retrieves the stored suspension and uses the exact stored tool call. The client cannot replace the reviewed arguments simply by sending a different free-text message.

The resume path accepts structured approve or deny responses. For compatibility, a small set of affirmative or negative text phrases can also be interpreted. Other non-empty feedback is treated as a denial response with feedback.

This protects the approved action binding but does not make the tool data non-sensitive.

## 23. MCP configuration

`neuron_mcp` can configure an optional external MCP connection for an agent.

The stored configuration may contain endpoint or command information plus tool allow or deny lists. NeuronAi's configuration layer rejects recursively detected secret-like keys, and runtime normalization removes direct `token`, `authorization` and `api_key` fields.

MCP credentials should therefore not be stored in the Neuron agent JSON.

## 24. MCP URL and command boundary

The normalized MCP configuration accepts either a remote `url` or a local `command`. A URL can create an external network boundary. A command can create a local process boundary through the third-party MCP connector.

The exact child-process, protocol and transport behavior is implemented by the pinned Neuron AI library and should be reviewed upstream. MCP configuration must therefore be treated as trusted administrative configuration, not ordinary end-user input.

## 25. MCP data transfer

When MCP is enabled, the upstream Neuron MCP connector can discover and invoke external MCP tools according to its supported behavior.

Tool names, arguments, results and protocol metadata can cross that MCP boundary. The exact protocol behavior is implemented by the third-party Neuron AI runtime and should be reviewed in the upstream documentation for the pinned version.

The external MCP server is an independent data-processing boundary and requires its own privacy, retention, authentication and logging assessment.

## 26. Suggestion mode

Suggestion mode deliberately disables executable BASE3 tools and MCP for the suggestion execution.

It can still load existing persistent conversation history as read-only model context. The suggestion request and generated suggestion result are discarded from Neuron conversation history, but they are still processed by the configured LLM provider for that request.

Provider-side retention is outside NeuronAi.

## 27. Text-task mode

`NeuronAgentTextTaskService` performs isolated model calls without persistent Neuron chat history and without tool execution.

The task can optionally include the current context profile and a catalog of available capability names, titles and descriptions. These values become part of the model input when enabled.

The task result can include provider usage metadata in the returned runtime metadata, but NeuronAi does not persist that result in its conversation table.

## 28. Provider-side retention and training

NeuronAi cannot determine whether an external LLM provider stores API requests, uses them for abuse monitoring, retains them for a defined period, or uses them for model improvement.

Those rules depend on the selected provider, account, contract, deployment region and provider settings.

Every production deployment should document the selected provider's:

- processing region;
- retention policy;
- training or product-improvement policy;
- subprocessors;
- deletion controls;
- cross-border transfers where applicable.

## 29. Errors and runtime diagnostics

NeuronAi can return exception messages, error types, warning lists, context diagnostics and tool diagnostics through runtime results and event sinks.

The component does not create a private general log table for this data. However, consumers may log or persist those results elsewhere.

Provider or MCP errors can include remote service details. Operational logging should therefore avoid indiscriminate storage of complete prompts, credentials, tool payloads or remote response bodies.

## 30. Configuration form processing

`NeuronAgentConfigFormService` reads the selected LLM, memory profile, context profile, tool profiles, instructions, maximum tool runs and MCP JSON from the host request abstraction.

The service validates referenced technical IDs and rejects secret-like MCP fields.

`neuron_instructions` can contain confidential system instructions. Access to agent configuration forms should therefore be restricted by the host administration layer that exposes them.

## 31. Sensitive MCP-key filtering

The current form validation treats keys such as these as sensitive:

- password and passwd;
- secret;
- token;
- API key variants;
- authorization;
- credential and credentials;
- private key;
- client secret;
- access token;
- auth token;
- bearer token.

It also rejects several corresponding suffix patterns recursively in nested MCP configuration.

This filter reduces accidental secret storage in agent JSON. It is not a general-purpose data-loss-prevention system.

## 32. Browser storage

NeuronAi itself does not implement a browser conversation client and does not create Local Storage, Session Storage or IndexedDB records.

A consuming chatbot or host UI may maintain browser-side identifiers or state. Those behaviors belong to that consuming component and should be documented there.

## 33. Cookies

NeuronAi does not set its own application cookie. It can depend on the existing BASE3 session service for anonymous conversation ownership.

Cookie creation and attributes are therefore part of the configured session and host stack.

## 34. File uploads and media

The current NeuronAi integration does not implement file upload storage or media ingestion.

If a future provider, tool or context contributor passes file-derived content into the runtime, that data flow belongs to the corresponding integration and must be documented separately.

## 35. Background processing

The current component defines no NeuronAi-specific worker job for conversation processing or retention.

Agent execution occurs through runtime service calls. Shared host infrastructure may schedule or invoke those services separately.

## 36. Database migrations

NeuronAi owns a migration provider for its private conversation schema. The migration adds conversation-list metadata such as title, title source, opening message and the conversation-list index.

The history schema can also ensure the private table when accessed.

The migration does not introduce a second persistence path for the same conversation data.

## 37. Access-control boundary

NeuronAi scopes persistent conversations by a server-derived user or session owner identity, but it does not define a complete application-level authorization model for every runtime feature.

For example:

- editing an agent configuration is governed by the host administration path;
- executing a concrete BASE3 tool is governed by the resolved shared tool set and its policies;
- accessing external MCP capabilities is governed by the MCP endpoint and runtime configuration;
- LLM connection access is governed by the model configuration provider.

Deployments must preserve those existing authorization boundaries rather than treating NeuronAi as a replacement for them.

## 38. Data minimization considerations

A deployment can reduce exposure by:

- selecting only the context profiles required for an agent;
- selecting only required tool profiles;
- limiting MCP tools with `only` and `exclude`;
- avoiding personal data in system instructions unless required;
- configuring short conversation retention where appropriate;
- preventing secrets from being entered into prompts or MCP configuration;
- using text tasks when persistent conversation history is not required;
- avoiding unnecessary remote providers for local-only workloads.

## 39. Security of transport

The integration accepts both HTTP and HTTPS configured LLM endpoints. For external or untrusted networks, HTTPS should be used.

MCP transport security depends on the selected connector configuration and the upstream Neuron AI implementation. Consult the upstream documentation and secure the endpoint according to the deployment environment.

## 40. Third-party dependency updates

Third-party runtime code is rebuilt from pinned build inputs and namespace-scoped into the plugin.

Privacy and security review should be repeated when the pinned Neuron AI version or one of its runtime dependencies changes. The manifest and Composer lock make that change set auditable.

Generated files below `src/Vendor` should not be manually patched as a substitute for updating or adapting the integration at its proper boundary.

## 41. Third-party telemetry

The embedded package inventory includes third-party code that may provide optional observability or HTTP features. NeuronAi does not document or promise the internal behavior of those packages here.

The BASE3 integration does not explicitly configure a separate Inspector telemetry service in its own source. Any upstream behavior activated by future configuration or upstream changes must be reviewed against the pinned runtime and deployment configuration.

## 42. Data-subject operations

At the component level, the runtime-neutral conversation service provides conversation listing, retrieval, rename and explicit deletion for the server-resolved owner scope.

NeuronAi does not implement a complete privacy-request workflow, global user-data export, backup erasure workflow or account-deletion orchestrator.

Those organization-level workflows must combine this component with the relevant user, host, database and backup systems.

## 43. Production deployment checklist

Before production use, document and verify at least:

- which LLM configuration is selected for each agent;
- where the LLM endpoint processes data;
- how LLM credentials are stored outside NeuronAi;
- whether HTTPS is enforced by deployment policy;
- whether persistent conversation memory is enabled;
- conversation retention and deletion procedures;
- backup and replica retention;
- which context profiles can expose personal data;
- which BASE3 tool profiles are assigned;
- which tools require approval;
- the concrete shared suspension repository and its cleanup behavior;
- whether MCP is enabled and which MCP endpoints receive data;
- provider and MCP retention policies;
- operational logging performed by event sinks or host infrastructure;
- authorization for agent configuration and conversation access;
- dependency update and third-party review procedures.

## 44. Related component documentation

For NeuronAi-specific integration details, see:

- [FAQ](docs/faq.md)
- `docs/ARCHITECTURE.md`
- `docs/CHAT_HISTORY.md`
- `docs/CONTEXT_PROFILES.md`
- `docs/DEPENDENCY_ISOLATION.md`
- `docs/DI.md`
- `docs/TOOLS.md`
- `docs/UPGRADE.md`
- `docs/UPSTREAM_CHANGES.md`
- `THIRD_PARTY/manifest.json`

For behavior inside the embedded Neuron AI runtime and its transitive dependencies, use the corresponding upstream documentation for the exact versions recorded in `THIRD_PARTY/manifest.json`.
