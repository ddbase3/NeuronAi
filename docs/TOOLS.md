# BASE3 tool profiles

## Purpose

NeuronAi can use the same stored tool profiles as MissionBay. The agent record
stores an ordered list of profile IDs in `tool_profiles`. For every execution,
AssistantRuntime resolves those profiles into a run-local `IAgentToolSet`.

The Neuron integration uses only public APIs:

- `AgentInterface::addTool()`;
- `Tool` and `ToolPropertyInterface` implementations;
- normal Neuron tool-call and tool-result messages and stream chunks.

No file below `src/Vendor` is changed.

## Read-only calls

Definitions explicitly annotated with `readOnlyHint=true` execute immediately.
Missing or ambiguous safety annotations remain excluded. Input and output
contracts are validated by the existing MissionBay contract validator before
and after the concrete tool call.

A failed execution is returned to Neuron as a structured tool result and emitted
as `tool.failed`; it does not silently become a successful observation.

## Mutating calls

A mutation is exposed only when its definition explicitly declares:

```text
mutation=true
requiresApproval=true
```

When `commitGuardRequired=true`, the concrete tool must also implement the
existing MissionBay mutation-guard contract. Otherwise the function is omitted
from the shared profile.

Neuron never executes such a function directly. The native tool adapter asks
the run-local `IAgentConfirmableToolSet` to create an exact suspension. The
chatbot receives the existing `agent.interaction.required` event and sends a
structured approve or deny response with the opaque resume handle.

After approval:

1. the server restores the exact reviewed call;
2. binding and fingerprint are checked;
3. the final commit guard runs;
4. the concrete tool executes at most once;
5. its `AgentToolResult` is returned to Neuron as a native tool-result message;
6. Neuron writes the final assistant response.

The original prompt, reviewed call and completed tool result remain server-owned.
Client input cannot replace the stored tool arguments.

## Schema mapping

`NeuronAgentTool` maps the existing OpenAI-style function schema to Neuron tool
properties. The original BASE3 schema remains authoritative and is validated at
execution time. Unsupported provider-side schema keywords are therefore not a
reason to weaken server-side validation.

## Tool execution audit context

The Neuron adapter passes run-local execution metadata through the existing
`IAgentToolSet` boundary. It contains the native call id, display label,
per-tool run number and global call index. The provider-owned tool set combines
it with the `AgentExecutionRequest` context:

- runtime and turn id;
- chatbot key;
- configuration group and name;
- conversation id;
- current user prompt.

This metadata is used for execution diagnostics, audit events and the chatbot
activity display. It is not copied into Neuron chat history.

## Upgrade checks

After a Neuron update, verify:

1. `AgentInterface::addTool()` accepts generated `Tool` instances;
2. `Tool::setResult()` still accepts serialized array results;
3. `ToolCallMessage` and `ToolResultMessage` still accept native tool objects;
4. `ToolCallChunk` and `ToolResultChunk` still expose `ToolInterface`;
5. a tool exception still reaches the adapter when no Neuron error handler is configured;
6. successful and failed tool results remain visible to the model;
7. no generated or manual change exists below `src/Vendor`.

## Approval-bound mutations

For a registered mutation tool with `requiresApproval=true`, the Neuron adapter
instructs the model to call the exact registered tool immediately after an
explicit user request. The model must not ask for a natural-language
confirmation and must never invent an alias for a tool name. The first tool
call creates an `AgentInteractionRequest`; the Chatbot renders the request as a
server-owned approval panel with **Zustimmen** and **Abbrechen** buttons.

Approval decisions are submitted as structured `AgentInteractionResponse`
values. Free text is not accepted while an approval-only interaction is
pending. This keeps the reviewed tool name and arguments bound to the stored
suspension instead of asking the model to interpret a later "yes" message.
