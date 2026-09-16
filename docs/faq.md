# NeuronAi FAQ

## What is NeuronAi?

NeuronAi is a BASE3 plugin that integrates the Neuron AI agent runtime with the runtime-neutral contracts provided by AssistantFoundation and the shared runtime services used by BASE3 AI applications.

The plugin is an integration and adapter layer. It does not redefine the Neuron AI framework itself.

## Is NeuronAi the same project as the upstream Neuron AI library?

No. The plugin contains BASE3-specific integration code and an isolated build of the upstream Neuron AI runtime.

The upstream runtime and its dependencies remain third-party software. Their APIs, internal behavior, supported features, security notes and licensing should be taken from the corresponding upstream projects.

## Where is the exact third-party software inventory documented?

The authoritative package inventory for the embedded runtime is:

- `THIRD_PARTY/manifest.json`
- `build/composer.lock`

`THIRD_PARTY/manifest.json` records the pinned upstream package, version, source reference, dependency versions, license identifiers and whether a package is embedded in the generated runtime.

The current manifest identifies Neuron AI `3.15.26` as the upstream runtime. The FAQ intentionally does not duplicate the documentation of Neuron AI, Guzzle, PSR packages, Inspector or other bundled libraries.

## How are third-party licenses handled?

The NeuronAi integration itself is licensed under GPL-3.0 as stated in the plugin `LICENSE` file and source headers.

Bundled third-party packages retain their own licenses. The package and declared license inventory is recorded in `THIRD_PARTY/manifest.json`. For authoritative license terms and notices of an upstream package, consult the corresponding upstream package and release source.

## Why is the third-party runtime stored below `src/Vendor`?

The embedded runtime is namespace-scoped below `NeuronAi\\Vendor`. This isolates it from package versions already present in a host application.

The build process uses the pinned Composer lock and PHP-Scoper to create this private runtime. Generated vendor files are not intended for manual editing.

See:

- `docs/DEPENDENCY_ISOLATION.md`
- `docs/UPGRADE.md`
- `docs/UPSTREAM_CHANGES.md`
- `THIRD_PARTY/manifest.json`

## Does NeuronAi modify the host Composer project?

No. Runtime dependencies are shipped in the plugin's private namespace. The normal BASE3 class loading mechanism loads the scoped classes.

NeuronAi does not register a second general Composer autoloader at runtime.

## What does `VendorBootstrap` do?

`VendorBootstrap` loads the small set of function-only compatibility files that cannot be discovered as classes. It also verifies the minimum PHP environment before the runtime is used.

This is part of dependency isolation, not an alternative dependency manager.

## What PHP version is required?

The current plugin requires PHP 8.1 or newer.

It also requires either native `mbstring` support or `iconv`. The embedded runtime includes scoped compatibility code for required functions where appropriate.

## What are the main responsibilities of the BASE3 integration code?

NeuronAi is responsible for the BASE3-facing runtime boundary, including:

- the runtime ID `neuronai`;
- provider creation from normalized BASE3 model configuration;
- agent creation;
- runtime configuration fields specific to NeuronAi;
- conversion of Neuron stream events into AssistantFoundation execution events;
- persistent Neuron chat history through the BASE3 database abstraction;
- runtime context profile integration;
- shared BASE3 tool profile integration;
- approval and resume handling for confirmable tools;
- optional outbound MCP configuration;
- isolated text-task execution.

## What does NeuronAi deliberately not own?

NeuronAi does not own the general administration of LLM connections and credentials, the HTTP transport of a chatbot UI, the shared runtime registry, or the implementation of generic BASE3 context and tool profiles.

It consumes those capabilities through AssistantFoundation and shared runtime contracts.

## What is the runtime identifier?

The runtime identifier is:

```text
neuronai
```

The runtime label is `Neuron AI`.

## How is an agent execution started?

`NeuronAgentExecutionService` receives an `AgentExecutionRequest`. It resolves the Neuron-specific configuration, current context profile, configured tool profiles and optional conversation memory. It then creates a run-scoped Neuron agent and always uses the Neuron streaming path for a normal agent execution.

The resulting chunks and terminal state are mapped to AssistantFoundation events and results.

## Which configured LLM drivers are supported by the current adapter?

The current `NeuronProviderFactory` maps these normalized BASE3 drivers:

- `openai-chat`
- `openai-compatible-chat`
- `mistral-chat`

Support for a driver here means that the BASE3 adapter knows how to construct the corresponding upstream provider class. The detailed provider behavior belongs to the upstream Neuron AI library.

## Where are model, endpoint and API key settings stored?

NeuronAi agent settings store only the selected configured LLM ID.

At execution time, `IAiModelConfigurationProvider` resolves the selected model and its connection data. This includes the provider driver, model name, endpoint, provider options and the resolved API key.

NeuronAi does not duplicate those credentials into its agent configuration.

## How are provider endpoints used?

`ConfiguredEndpointHttpClient` sends Neuron chat-completions requests to the exact endpoint supplied by the normalized BASE3 model configuration.

The endpoint must be an absolute `http` or `https` URL and must not contain a fragment. The adapter accepts both HTTP and HTTPS, so secure deployments should configure HTTPS when data leaves a trusted local boundary.

## Which Neuron-specific agent settings exist?

The runtime currently uses these settings in addition to the selected `llm`:

- `memory_profile`
- `context_profile`
- `tool_profiles`
- `neuron_instructions`
- `neuron_max_tool_runs`
- `neuron_mcp`

The configuration form validates referenced LLM, context and tool profile identifiers against the shared runtime services.

## What is the default memory profile?

The default profile is:

```text
neuronai-database
```

It uses the plugin's native database-backed Neuron chat history.

## Where is persistent conversation history stored?

Persistent history is stored in:

```text
base3_neuronai_chathistory
```

One row represents one scoped conversation and stores Neuron-native serialized message history plus conversation metadata.

## What metadata is stored with a conversation?

The table contains, among other fields:

- a SHA-256 conversation key;
- the external conversation ID;
- a SHA-256 owner key;
- configuration group and configuration name;
- runtime ID;
- title and title source;
- optional opening message;
- serialized message history;
- message count;
- optimistic version number;
- creation, update and last-access timestamps.

## How is conversation ownership isolated?

The caller supplies the conversation ID and chatbot or configuration identity, but not the conversation owner.

`NeuronConversationOwnerResolver` derives the owner identity on the server from either:

1. the authenticated BASE3 user ID; or
2. the active BASE3 session ID for an anonymous user.

The resulting identity is SHA-256 hashed before it becomes the stored `owner_key`.

`NeuronConversationKeyFactory` combines runtime, owner key, configuration identity and conversation ID into the final conversation key.

## Is the raw user ID or session ID stored in the Neuron history table?

No. The table stores the SHA-256 `owner_key` derived from the server-side identity.

This is a pseudonymous technical identifier, not an anonymization guarantee. A deployment that knows the original user or session namespace may still be able to associate records with a person or session.

## Which conversation operations are supported?

`NeuronAgentConversationService` provides the runtime-neutral conversation operations used by the shared conversation API, including:

- list and state retrieval;
- create;
- activate or touch;
- rename;
- delete;
- append a visible user or assistant message.

The service uses the same canonical history table as agent execution.

## Does deleting a conversation delete the history row?

Yes. `deleteConversation()` removes the scoped row from `base3_neuronai_chathistory`.

NeuronAi does not currently provide an automatic retention job for old conversations. Retention beyond explicit deletion must therefore be defined at deployment level if required.

## How is concurrent conversation writing handled?

The current implementation uses optimistic versioning on the canonical database row.

A history update succeeds only for the expected version. If another request changed the row first, the repository detects the conflict and fails instead of silently overwriting the other turn.

NeuronAi does not maintain a second conversation lock in its current source implementation.

## Are partial or failed turns written to persistent history?

The Neuron chat history buffers message changes for the current run.

The history is committed only after a normal execution produces a non-empty final assistant message. Failed or cancelled runs discard the buffered history. This prevents an incomplete user-only turn from being persisted as the final state of the conversation.

## What happens when a persisted history ends with an incomplete user message?

When a conversation is loaded, the repository can remove an incomplete tail before continuing. This protects subsequent execution from a broken alternating history caused by older or interrupted writes.

## Who owns Neuron message serialization and context-window behavior?

The embedded upstream Neuron AI runtime owns its native message objects, serialization, tool-call and tool-result message format, token accounting and context-window trimming.

NeuronAi only adapts the public chat-history extension point to BASE3 persistence. For the exact upstream semantics, consult the Neuron AI project documentation for the pinned version.

## What are context profiles?

A `context_profile` is resolved for every execution through the runtime-neutral `IAgentContextProfileService`.

The resulting instruction blocks are appended to the run-scoped Neuron instructions. They are intentionally not added to persistent chat history.

This lets dynamic context change from turn to turn without becoming stale conversation memory.

## Can context-profile data still be sent to the LLM?

Yes. Although dynamic context is not persisted in Neuron history, it becomes part of the instructions for the current model request. Any sensitive data produced by a context provider can therefore cross the configured LLM provider boundary.

The context provider is responsible for producing appropriate content for that use.

## How are context-profile failures handled?

An unknown or disabled selected profile fails the execution. Individual contributor failures can be represented as warnings while other valid context blocks remain available.

Diagnostics report identifiers, sources and lengths rather than copying the full context content.

## What are direct BASE3 tools?

NeuronAi can receive a run-local `IAgentToolSet` produced from shared BASE3 tool profiles. `NeuronAgentTool` maps a runtime-neutral capability into the public Neuron tool API.

The original BASE3 capability definition remains authoritative for server-side execution and validation.

## Are tool schemas reimplemented by NeuronAi?

No. The adapter maps the relevant OpenAI-style function schema into Neuron tool property objects so the upstream runtime can call the tool.

Actual execution remains behind the shared BASE3 tool-set boundary. Input and output rules are owned by the originating tool system and its contracts.

## How are mutating tools handled?

Confirmable tools use the shared `IAgentConfirmableToolSet` and suspension lifecycle.

A tool that requires approval is not directly committed by the Neuron adapter. The server creates an exact suspension for the reviewed call. The host interaction flow can then approve or deny that stored call.

The resumed execution uses the server-owned tool name and arguments from the suspension rather than accepting replacement arguments from a later client message.

## How long is a Neuron tool suspension created for?

`NeuronAgentExecutionService` currently creates a suspension with a TTL of 900 seconds, or 15 minutes.

The actual storage backend for suspensions is provided by the shared `IAgentSuspensionRepository`, not by a private NeuronAi suspension table.

## What can be stored in a pending suspension?

The Neuron continuation state includes the original prompt and already completed tool results. The shared suspension also contains the pending tool call and interaction request data required for approval and resume.

This data may be sensitive. The concrete shared suspension repository and its deployment retention behavior must therefore be treated as part of the overall privacy boundary.

## Does NeuronAi support MCP?

Yes. An agent may contain a non-secret `neuron_mcp` configuration. The upstream Neuron MCP connector is then attached to the agent.

NeuronAi can restrict exposed MCP tools with `only` and `exclude` lists.

The implementation intentionally does not document the internal behavior of the third-party MCP client. Refer to the pinned Neuron AI documentation for its protocol behavior and supported connector options.

## Can MCP use a URL or a local command?

Yes. The normalized `neuron_mcp` configuration accepts either a `url` or a `command`. The third-party Neuron MCP connector determines the concrete transport and process behavior.

A command-based MCP configuration can therefore introduce a local process boundary. Only trusted configuration should be allowed to define MCP commands, and the upstream Neuron AI documentation for the pinned version should be used to review the exact behavior.

## May MCP credentials be stored in `neuron_mcp`?

No. The NeuronAi configuration form rejects recursively detected secret-like keys such as passwords, API keys, tokens, bearer tokens, private keys and client secrets.

Normalization also removes `token`, `authorization` and `api_key` keys from MCP configuration before execution.

Credentials for an MCP endpoint must therefore be supplied outside the stored Neuron agent JSON through an appropriate host or endpoint mechanism.

## Is MCP enabled during suggestion generation?

No. Suggestion mode removes executable tool profiles and MCP configuration for that execution.

## What is suggestion mode?

Suggestion mode is a normal model execution that may use the current conversation history as read-only context, but does not expose executable tools or MCP and does not persist the temporary suggestion turn to conversation history.

The provider still processes the model request and the active conversation context needed to generate the suggestions.

## What are Neuron text tasks?

`NeuronAgentTextTaskService` provides isolated text-generation tasks through the same configured runtime.

Text tasks do not attach persistent chat history and do not execute tools. They can optionally include a current context profile and a textual catalog of configured capabilities, depending on the request.

## Does including a tool profile in a text task execute those tools?

No. The service can add capability names, titles and descriptions to the instructions so the model can reason about available capabilities, but it clears executable tools for the text task itself.

## How are streaming events exposed?

The upstream Neuron event stream is mapped by `NeuronExecutionEventMapper` into AssistantFoundation execution events.

The supplied `IAgentEventSink` decides how those events are delivered, collected or discarded. NeuronAi itself does not own a browser SSE endpoint or a UI transport.

## Can execution be cancelled?

Yes, if the supplied event sink reports cancellation. The run stops consuming the stream and the buffered chat history is discarded for that execution.

## Does NeuronAi implement a browser UI?

No general chat UI is implemented by NeuronAi. It provides a server-side runtime adapter and a runtime configuration form template.

Browser transport, conversation UI behavior and browser storage are owned by consuming components.

## Does NeuronAi use cookies or browser storage itself?

The server-side plugin does not create its own cookies, Local Storage or IndexedDB data.

It can use the existing BASE3 session service when an anonymous conversation needs a server-owned conversation owner identity.

## Does the plugin store its own LLM API keys?

No. It receives resolved model configuration through `IAiModelConfigurationProvider` and uses the API key in memory when creating the provider.

Credential storage, rotation and administration belong to the configured model and connection infrastructure.

## Does the plugin have its own general log store?

No private application log or audit table is implemented in NeuronAi.

Execution errors, warnings, tool activity and stream events can be returned through the shared execution and event-sink contracts. Consuming infrastructure may persist or log those values separately.

## Does the plugin run background jobs?

No NeuronAi-specific worker job is present in the current component.

## Does the plugin define database migrations?

Yes. `NeuronAiMigrationProvider` provides the conversation metadata migration and is currently active. The history schema can also ensure its private table when needed.

The schema is owned by NeuronAi because it stores Neuron-specific persistent conversation state.

## Does NeuronAi expose a general administration endpoint?

The component provides a runtime configuration form service and template but does not define a standalone general administration application with its own user-management model.

Authorization for editing agent configurations belongs to the host administration path that exposes the shared runtime form.

## Can NeuronAi be used without persistent conversation memory?

The execution configuration model supports an empty memory profile, and the text-task runtime deliberately runs without persistent chat history.

For normal configured conversations, the built-in and default profile is `neuronai-database`.

## What happens if the selected LLM or profile is missing?

Configuration and execution fail explicitly. The adapter does not silently choose a different LLM, context profile, tool profile or memory backend.

## How should the bundled runtime be updated?

Update the pinned build inputs and rebuild the generated runtime with the provided build process. Do not patch files below `src/Vendor` manually to preserve compatibility.

After an upstream update, run the documented verification and smoke tests and review upstream changes that affect the public APIs used by the integration.

## Where should I read about Neuron AI framework features that are not BASE3 integration concerns?

Use the upstream Neuron AI documentation for the pinned version.

Within this plugin, use:

- `THIRD_PARTY/manifest.json` for exact package inventory;
- `docs/ARCHITECTURE.md` for the adapter boundary;
- `docs/DEPENDENCY_ISOLATION.md` for vendor isolation;
- `docs/CHAT_HISTORY.md` for the persistence adapter;
- `docs/CONTEXT_PROFILES.md` for runtime context integration;
- `docs/TOOLS.md` for BASE3 tool integration;
- `docs/UPGRADE.md` and `docs/UPSTREAM_CHANGES.md` for runtime maintenance.
