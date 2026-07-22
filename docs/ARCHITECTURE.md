# Architecture

## Boundary

NeuronAi is an adapter between independent contracts:

- AssistantFoundation defines transport-neutral execution and normalized LLM
  configuration contracts;
- AssistantRuntime provides runtime registry, routing and form composition;
- Neuron AI provides the concrete agent, provider, streaming and MCP APIs.

NeuronAi does not read Chatbot, EventTransport, MissionBay or ILIAS classes.
The currently installed configured-LLM adapter may live in another plugin and is
received only through `IAiModelConfigurationProvider`.

## Runtime path

One execution follows this path:

1. `NeuronAgentExecutionService` receives `AgentExecutionRequest`.
2. `NeuronAgentConfiguration` reads the selected configured LLM ID, context
   profile and Neuron-specific behavior settings.
3. `IAgentContextProfileService` resolves run-local instruction blocks.
4. `NeuronContextInstructionsBuilder` appends those blocks to the current
   Neuron instructions without writing them to chat history.
5. `INeuronAgentFactory` creates one run-scoped Neuron agent.
6. `INeuronProviderFactory` resolves the selected LLM through
   `IAiModelConfigurationProvider` and creates the matching Neuron provider.
7. The configured LLM adapter resolves its referenced connection and credential.
8. The execution service always invokes Neuron's streaming API.
9. `NeuronExecutionEventMapper` converts chunks into `AgentExecutionEvent`
   objects.
10. The supplied `IAgentEventSink` forwards, collects or discards events.
11. The terminal message is converted into `AgentExecutionResult` and
   `AgentResult`.

REST and SSE therefore use the same runtime implementation.

## Extension points

### `IAiModelConfigurationProvider`

Replace the host binding when configured LLMs and connections come from another
administration plugin. NeuronAi stores and passes only the selected LLM ID.

### `INeuronProviderFactory`

Replace this service when additional configured LLM drivers need a custom
Neuron provider mapping.

### `INeuronAgentFactory`

Replace this service when the host must add memory, middleware, direct tools,
MCP connectors or specialized Agent subclasses. The execution request exposes
run-scoped inputs and context without coupling the implementation to ILIAS.

### `NeuronAgentExecutionService`

The service is discovered as `IAgentRuntimeService`. The shared router selects
it when stored `agent_runtime` is `neuronai`.

### `NeuronAgentConfigFormService`

Provides configured-LLM and context-profile selection plus Neuron instructions,
tool-run and MCP fields. It is paired with execution through the stable runtime
ID `neuronai`.

## Tool integration stage

The pilot exposes the existing tool world through MCP. The target architecture
will provide a common executable tool catalog and policy layer outside the
individual runtimes. MCP then remains an optional remote-tool adapter rather
than the only integration path.
