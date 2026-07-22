# Dependency injection

## Default plugin wiring

`NeuronAiPlugin` registers all defaults with `IContainer::NOOVERWRITE`:

- `INeuronProviderFactory` → `NeuronProviderFactory`;
- `INeuronAgentFactory` → `NeuronAgentFactory`;
- `NeuronExecutionEventMapper`;
- `NeuronAgentExecutionService`;
- `NeuronAgentConfigFormService`.

The plugin does not bind generic runtime facades. `AssistantRuntime` owns
`IAgentExecutionService`, `IAgentConfigFormService`, discovery and routing.

## Configured LLM boundary

NeuronAi does not read SettingsStore groups, connection records or secret
formats directly. `NeuronProviderFactory` requests a normalized model through:

```php
interface IAiModelConfigurationProvider {
    public function getOptions(): array;
    public function has(string $id): bool;
    public function get(string $id): AiModelConfiguration;
}
```

`AiModelConfiguration` contains the resolved driver, model, endpoint, options
and credential needed for one execution. The stored Neuron agent only contains
the configured LLM ID.

The current BASE3 wiring supplies
`MissionBay\Service\ConfiguredAiModelConfigurationProvider`, which adapts the
existing `service-llm` and referenced `connection` records. This is an adapter
to existing administration data, not a dependency of NeuronAi code on
MissionBay classes.

A future standalone LLM configuration plugin can replace this binding with
`NOOVERWRITE` semantics unchanged.

## Runtime selection

`NeuronAgentExecutionService` and `NeuronAgentConfigFormService` expose runtime
ID `neuronai`. `AssistantRuntime\Service\AgentRuntimeRegistry` accepts the
runtime only when execution and form services are present.

Agent Admin stores `agent_runtime=neuronai`. Chatbot stores one combined backend
selection and delegates to the same runtime router.
