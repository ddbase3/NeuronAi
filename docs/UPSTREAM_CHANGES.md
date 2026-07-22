# Upstream integration and local changes

## Upstream baseline

- Package: `inspector-apm/neuron-ai`
- Version: `3.15.26`
- Source reference: `ae5be8f065b19c9b7a5adff596b1d04fc07daf67`
- Upstream namespace: `NeuronAI`
- Embedded namespace: `NeuronAi\Vendor\NeuronAI`

Exact package versions and source references are stored in
`THIRD_PARTY/manifest.json` and `build/composer.lock`.

## Generated vendor transformations

No file below `src/Vendor` is edited manually. The deterministic build:

1. prefixes selected package namespaces with `NeuronAi\Vendor` using PHP-Scoper;
2. copies required Composer file-autoload support code;
3. adjusts the scoped Symfony Mbstring bootstrap reference;
4. copies license and package metadata;
5. validates namespaces and PHP syntax before replacing the installed tree.

## BASE3 adapter code

Local files to review during every Neuron update:

- `NeuronAiPlugin.php`: defensive default DI;
- `VendorBootstrap.php`: embedded dependency function bootstrap;
- `Api/INeuronProviderFactory.php`: provider construction extension point;
- `Api/INeuronAgentFactory.php`: agent construction extension point;
- `Api/INeuronChatHistoryFactory.php`: persistent history extension point;
- `Dto/NeuronAgentConfiguration.php`: configured-LLM and runtime normalization;
- `Service/NeuronProviderFactory.php`: maps normalized configured LLMs to Neuron providers;
- `Service/NeuronAgentFactory.php`: agent and optional MCP setup;
- `Chat/History/DatabaseNeuronChatHistory.php`: public `AbstractChatHistory` adapter;
- `Service/NeuronChatHistoryFactory.php`: history, locking and context-window setup;
- `Service/NeuronChatHistoryRepository.php`: dedicated BASE3 database persistence;
- `Service/NeuronConversationKeyFactory.php`: conversation-scope isolation;
- `Service/NeuronExecutionEventMapper.php`: event translation;
- `Service/NeuronAgentExecutionService.php`: BASE3 runtime adapter;
- `Service/NeuronAgentConfigFormService.php`: runtime form normalization;
- `tpl/Content/NeuronAgentConfigFormSection.php`: runtime form presentation.

The runtime-neutral configured-LLM contract is located in AssistantFoundation:

- `Api/IAiModelConfigurationProvider.php`;
- `Dto/AiModelConfiguration.php`.

The current adapter for existing BASE3 LLM and connection records is:

- `MissionBay/Service/ConfiguredAiModelConfigurationProvider.php`.

## Local architectural rules

- Neuron agent records store only a configured LLM ID, never duplicate provider
  or connection data.
- Connection credentials are resolved only during execution.
- Runtime selection is owned by `AssistantRuntime`, not NeuronAi.
- Chatbot transport and POST→ID→GET long-prompt handling remain outside
  NeuronAi.
- Existing tools are currently connected through MCP. Direct shared tool
  execution remains a later step.

## BASE3 endpoint adaptation

BASE3 LLM connections store the complete HTTP request endpoint. Neuron providers
expect an API base URI and append `chat/completions` themselves. Path inference is
not reliable for proxies and provider-specific gateways.

`NeuronAi\Http\ConfiguredEndpointHttpClient` therefore ignores the provider's
base URI and maps Neuron's relative `chat/completions` request to the exact
absolute endpoint stored in the selected BASE3 connection. The client is injected
through the public Neuron provider constructor. No vendored Neuron source file is
modified. This local adapter and its smoke coverage must be retained during every
Neuron upgrade.


## Chat-history adaptation

Persistent memory is implemented only through Neuron's public
`AbstractChatHistory` and `AgentInterface::setChatHistory()` APIs. The adapter
stores Neuron's own serialized message array in
`base3_neuronai_chathistory`. No upstream message class or history class is
patched. See `docs/CHAT_HISTORY.md` for schema, locking and upgrade checks.
