# Context profiles

## Purpose

Neuron conversation history and runtime context are intentionally separate:

- Neuron chat history stores the persistent user, assistant and future tool
  messages of one conversation;
- a context profile is resolved again for every execution and contributes
  current system instructions such as time, page location or user preferences.

Dynamic context is never appended to the Neuron chat history. A page change or
clock change therefore takes effect on the next turn without leaving stale
context messages in the persisted conversation.

## Runtime-neutral contract

The integration uses only AssistantFoundation contracts:

- `IAgentContextProfileProvider` exposes one profile source;
- `IAgentContextProfileService` combines all providers into one unique profile
  namespace;
- `AgentContextProfileResult` contains ordered `AgentInstructionBlock` objects
  and non-fatal warnings.

The concrete provider registry is implemented by AssistantRuntime. NeuronAi
does not depend on MissionBay classes.

## Current profile provider

MissionBay exposes its existing `agent-context-profile` records through
`MissionBayContextProfileProvider`. The provider uses the existing component
preset materializer, so configured context contributors keep their normal
configuration, dock initialization and priority order.

This means the same stored profile ID can be selected for MissionBay and
NeuronAi agents. Typical contributors include:

- current date, time and timezone;
- current ILIAS page information;
- static context text;
- user preferences.

A later plugin may implement another `IAgentContextProfileProvider` without
changing NeuronAi. Profile IDs must remain globally unique.

## Neuron mapping

`NeuronAgentExecutionService` resolves the selected `context_profile` before
creating the Neuron agent. `NeuronContextInstructionsBuilder` appends the
resulting blocks to the run-scoped Neuron instructions in explicit `<CONTEXT>`
sections.

Only public Neuron APIs are used:

- `Agent::setInstructions()`;
- the normal agent streaming execution path.

No file below `src/Vendor` is modified.

## Failure behavior

- an empty profile ID means no runtime context;
- an unknown or disabled profile fails the execution with a visible error;
- individual contributor failures are returned as warnings and do not suppress
  other valid context blocks;
- context diagnostics contain IDs, sources and lengths, never the context
  content itself.

## Upgrade checks

After a Neuron upgrade, verify:

1. `Agent::setInstructions()` still accepts the complete run-scoped string;
2. context instructions are sent on every turn but are not serialized into chat
   history;
3. memory still contains only conversation messages;
4. the context-profile smoke test still observes the injected block;
5. no generated or manual changes exist below `src/Vendor`.
