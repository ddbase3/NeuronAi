# Changelog

## 0.4.2 - 2026-07-22

- Added `ConfiguredEndpointHttpClient` so Neuron sends chat requests to the exact endpoint stored in the selected BASE3 connection.
- Removed API-base path heuristics from `NeuronProviderFactory`.
- Kept all endpoint adaptation outside the vendored Neuron source tree.
- Extended the CLI smoke test with exact endpoint mapping coverage.

## 0.4.1 - 2026-07-22

- Treat configured BASE3 connection URLs as complete chat-completions endpoints and normalize them to the API base URI expected by Neuron.
- Renamed the neutral model DTO field from `baseUri` to `endpoint` to reflect the stored BASE3 connection contract.
- Added smoke coverage for OpenAI, Mistral and OpenAI-compatible full endpoint URLs.

## 0.4.0 - 2026-07-22

- Replaced Neuron-specific provider, model, endpoint and credential settings
  with selection of an existing configured LLM.
- Added the runtime-neutral configured-model contract in AssistantFoundation.
- Resolve driver, model, endpoint, options and connection credential only when
  an agent executes.
- Removed environment-secret and ILIAS-specific Neuron credential adapters.
- Kept MCP configuration non-secret and documented the update boundary.

## 0.3.0 - 2026-07-22

- Moved shared runtime registry, routing, form composition and event helpers to
  the new `AssistantRuntime` plugin.
- Added `INeuronRuntimeSecrets` and removed provider credentials from agent and
  chatbot records.
- Added host-side credential resolution for provider keys and MCP tokens.
- Added request parsing that validates only the actively selected runtime.
- Documented the ILIAS HTML credential configuration and upgrade boundary.

## 0.2.0 - 2026-07-22

- Registered Neuron AI as a named `IAgentRuntimeService` with runtime ID
  `neuronai` instead of competing for the generic execution binding.
- Added `NeuronAgentConfigFormService` and the Neuron runtime form used by both
  Chatbot configuration and Agent Admin.
- Added provider, model, parameter, tool-run and MCP configuration fields.
- Added a runtime-neutral provider/model summary for shared administration UIs.
- Kept provider and MCP credentials out of agent settings and rejected secret-like JSON keys in the shared form.
- Documented shared runtime routing and host-specific selection policy.

## 0.1.1 - 2026-07-21

- Made `test/smoke.php` inert when BASE3 or ILIAS includes it while discovering
  plugin PHP files.
- The smoke test now executes only when invoked directly from CLI.
- Replaced top-level test classes with anonymous test doubles scoped to the
  direct CLI execution.

## 0.1.0 - 2026-07-21

- Added the BASE3 plugin and default dependency-injection bindings.
- Added `NeuronAgentExecutionService` for the unified AssistantFoundation
  execution contract.
- Added REST/SSE-neutral stream event translation.
- Added request-aware provider and agent factory extension points for host-specific DI.
- Added provider support for OpenAI, Anthropic, Gemini, Mistral, DeepSeek,
  xAI, Ollama and OpenAI-compatible endpoints.
- Added an optional MCP bridge for the existing BASE3 tool world.
- Embedded Neuron AI 3.15.26 and all runtime dependencies under the isolated
  `NeuronAi\Vendor` namespace.
- Added a conditional Mbstring compatibility layer for standalone BASE3 hosts.
- Added reproducible vendor build, validation and upgrade documentation.
