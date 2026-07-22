# Upgrade procedure

## 1. Select and review the upstream release

Before changing the pin:

1. read the Neuron AI release notes and upgrade guide;
2. compare its PHP requirement with supported ILIAS and standalone BASE3;
3. review provider, streaming, MCP, tool and message API changes;
4. review all new or removed transitive runtime packages and their licenses.

## 2. Update the build lock

Change the exact Neuron version in `build/composer.json`, then run:

```bash
cd NeuronAi/build
composer update inspector-apm/neuron-ai --with-all-dependencies
```

Commit both `composer.json` and `composer.lock`.

## 3. Update the reviewed package set

Run:

```bash
php validate-lock.php
```

The command fails when the runtime dependency set changed. Review every added,
removed or renamed package. Then update:

- the package allow-list in `validate-lock.php`;
- source-copy rules in `build-vendor.sh`;
- namespace verification rules when needed;
- dependency and license documentation.

Do not merely add unknown packages to the allow-list.

## 4. Rebuild the embedded runtime

From the repository root:

```bash
./build/build-vendor.sh
```

The command recreates `src/Vendor`, `THIRD_PARTY/manifest.json` and copied
license files. The generated tree must not be edited manually.

## 5. Review local adapter compatibility

Review each file listed in `docs/UPSTREAM_CHANGES.md`, especially:

- provider constructor signatures;
- `Agent::stream()` and handler APIs;
- stream chunk classes and public properties;
- `McpConnector` construction and filtering;
- tool call/result serialization;
- terminal message content access;
- `AbstractChatHistory` protected persistence hooks;
- message JSON serialization and deserialization;
- `AgentInterface::setChatHistory()` compatibility.

## 6. Run checks

At minimum:

```bash
./build/verify-vendor.sh
php test/smoke.php
```

Also run the full BASE3/ILIAS integration tests for:

- Neuron REST chat;
- Neuron SSE chat;
- large prompt POST-to-ID-to-GET flow;
- SSE disconnect without duplicate execution;
- MCP read-only tool invocation;
- runtime registry discovery of both MissionBay and Neuron AI;
- Chatbot and Agent Admin runtime selection;
- scheduled jobs honoring the stored runtime;
- existing records without `agent_runtime` remaining on MissionBay;
- persistent Neuron history across separate REST and SSE requests;
- owner, chatbot and conversation isolation;
- conversation locking and optimistic-version conflicts.

## 7. Update documentation

Update:

- `VERSION`
- `CHANGELOG.md`
- upstream version and reference in `docs/UPSTREAM_CHANGES.md`
- known limitations when behavior changed.
