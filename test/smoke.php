<?php declare(strict_types=1);

/**
 * CLI-only smoke test.
 *
 * BASE3 may inspect PHP files while building its class map. Therefore this
 * executable test must remain inert unless it is invoked directly from CLI.
 */
if (
	PHP_SAPI !== 'cli'
	|| !isset($_SERVER['SCRIPT_FILENAME'])
	|| realpath((string) $_SERVER['SCRIPT_FILENAME']) !== __FILE__
) {
	return;
}

(static function(array $arguments): void {
	if (!interface_exists('Base3\\CoreFoundation\\Api\\IBase')) {
		eval(<<<'PHP'
namespace Base3\CoreFoundation\Api;

interface IBase {
	public static function getName(): string;
}
PHP);
	}

	$pluginDir = dirname(__DIR__);
	$foundationDir = $arguments[1] ?? dirname($pluginDir) . '/AssistantFoundation/src';
	$runtimeDir = $arguments[2] ?? dirname($pluginDir) . '/AssistantRuntime/src';
	if (!is_dir($foundationDir)) {
		fwrite(STDERR, "AssistantFoundation source directory not found. Pass it as first argument.\n");
		exit(1);
	}

	spl_autoload_register(static function(string $class) use ($pluginDir, $foundationDir, $runtimeDir): void {
		$prefixes = [
			'NeuronAi\\Vendor\\' => $pluginDir . '/src/Vendor/',
			'Base3\\NeuronAi\\' => $pluginDir . '/src/',
			'Base3\\AssistantFoundation\\' => $foundationDir . '/',
			'Base3\\AssistantRuntime\\' => $runtimeDir . '/'
		];
		foreach ($prefixes as $prefix => $directory) {
			if (!str_starts_with($class, $prefix)) {
				continue;
			}
			$file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require_once $file;
			}
			return;
		}
	});

	\NeuronAi\VendorBootstrap::init();

	$provider = new class implements \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface {

		public ?string $lastSystemPrompt = null;
		public array $lastTools = [];
		public int $streamCalls = 0;

		public function systemPrompt(?string $prompt): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { $this->lastSystemPrompt = $prompt; return $this; }
		public function setTools(array $tools): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { $this->lastTools = $tools; return $this; }
		public function messageMapper(): \NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface { throw new \LogicException('Not used by the smoke provider.'); }
		public function toolPayloadMapper(): \NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface { throw new \LogicException('Not used by the smoke provider.'); }
		public function chat(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message {
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Hello world');
		}
		public function stream(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \Generator {
			$this->streamCalls++;
			if ($this->streamCalls === 1 && isset($this->lastTools[0])) {
				$tool = clone $this->lastTools[0];
				$tool->setCallId('smoke-tool-call')->setInputs(['value' => 'Atlas']);
				return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage(null, [$tool]);
			}
			yield new \NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk('provider-message', 'Tool result accepted');
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Tool result accepted');
		}
		public function structured(array|\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message $messages, string $class, array $response_schema): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message {
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Hello world');
		}
		public function setHttpClient(\NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface $client): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
	};

	$providerFactory = new class($provider) implements \Base3\NeuronAi\Api\INeuronProviderFactory {
		public function __construct(private readonly \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface $provider) {}
		public static function getName(): string { return 'fakeproviderfactory'; }
		public function create(\Base3\NeuronAi\Dto\NeuronAgentConfiguration $configuration, \Base3\AssistantFoundation\Dto\AgentExecutionRequest $request): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface {
			return $this->provider;
		}
	};

	$request = new \Base3\AssistantFoundation\Dto\AgentExecutionRequest([
		'llm' => 'fake-llm',
		'context_profile' => 'smoke-context',
		'tool_profiles' => ['smoke-tools']
	], [
		'system' => 'Be concise.',
		'prompt' => 'Say hello.'
	], [
		'chatbot_config_group' => 'copg-chatbot',
		'chatbot_config_name' => 'pc-smoke',
		'conversation_id' => 'conversation-smoke'
	]);
	$chatHistoryFactory = new class implements \Base3\NeuronAi\Api\INeuronChatHistoryFactory {
		public static function getName(): string { return 'nullneuronchathistoryfactory'; }
		public function create(
			\Base3\NeuronAi\Dto\NeuronAgentConfiguration $configuration,
			\Base3\AssistantFoundation\Dto\AgentExecutionRequest $request
		): ?\Base3\NeuronAi\Dto\NeuronChatHistoryLease {
			return null;
		}
	};
	$contextProfileService = new class implements \Base3\AssistantFoundation\Api\IAgentContextProfileService {
		public static function getName(): string { return 'emptyagentcontextprofileservice'; }
		public function getOptions(): array { return [['id' => 'smoke-context', 'label' => 'Smoke context']]; }
		public function hasProfile(string $profileId): bool { return $profileId === 'smoke-context'; }
		public function build(
			string $profileId,
			\Base3\AssistantFoundation\Dto\AgentExecutionRequest $request
		): \Base3\AssistantFoundation\Dto\AgentContextProfileResult {
			return new \Base3\AssistantFoundation\Dto\AgentContextProfileResult(
				$profileId,
				[new \Base3\AssistantFoundation\Dto\AgentInstructionBlock(
					'smoke-current-page',
					'Current page is Smoke Test.',
					10,
					'smoke'
				)]
			);
		}
	};
	$capability = new \Base3\AssistantFoundation\Dto\AgentCapability(
		'smoke_echo',
		'Smoke echo',
		'Echoes one input value.',
		'test',
		['smoke'],
		0,
		[
			'type' => 'function',
			'readOnlyHint' => true,
			'function' => [
				'name' => 'smoke_echo',
				'description' => 'Echoes one input value.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'value' => ['type' => 'string']
					],
					'required' => ['value']
				]
			]
		]
	);
	$toolSet = new class($capability) implements \Base3\AssistantFoundation\Api\IAgentToolSet {
		private \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog $catalog;
		public array $metadata = [];
		public function __construct(\Base3\AssistantFoundation\Dto\AgentCapability $capability) {
			$this->catalog = new \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog([$capability]);
		}
		public function getCatalog(): \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog { return $this->catalog; }
		public function getWarnings(): array { return []; }
		public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): \Base3\AssistantFoundation\Dto\AgentToolResult {
			$this->metadata = $metadata;
			return \Base3\AssistantFoundation\Dto\AgentToolResult::success(
				$callId,
				$toolName,
				$arguments,
				['echo' => $arguments['value'] ?? null]
			);
		}
	};
	$toolProfileService = new class($toolSet) implements \Base3\AssistantFoundation\Api\IAgentToolProfileService {
		public function __construct(private readonly \Base3\AssistantFoundation\Api\IAgentToolSet $toolSet) {}
		public static function getName(): string { return 'smoketoolprofileservice'; }
		public function getOptions(): array { return [['id' => 'smoke-tools', 'label' => 'Smoke tools']]; }
		public function hasProfile(string $profileId): bool { return $profileId === 'smoke-tools'; }
		public function resolve(array $profileIds, \Base3\AssistantFoundation\Dto\AgentExecutionRequest $request): \Base3\AssistantFoundation\Api\IAgentToolSet {
			return $this->toolSet;
		}
	};
	$toolFactory = new \Base3\NeuronAi\Service\NeuronAgentToolFactory();
	$suspensionRepository = new class implements \Base3\AssistantFoundation\Api\IAgentSuspensionRepository {
		public function create(\Base3\AssistantFoundation\Dto\AgentSuspension $suspension, int $ttlSeconds): string { throw new \LogicException('Not used by the read-only smoke test.'); }
		public function findPending(string $scopeId): ?\Base3\AssistantFoundation\Dto\AgentSuspensionState { return null; }
		public function claim(string $resumeHandle): \Base3\AssistantFoundation\Dto\AgentSuspensionClaim { throw new \LogicException('Not used by the read-only smoke test.'); }
		public function release(\Base3\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
		public function consume(\Base3\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
	};
	$service = new \Base3\NeuronAi\Service\NeuronAgentExecutionService(
		new \Base3\NeuronAi\Service\NeuronAgentFactory($providerFactory, $toolFactory),
		$chatHistoryFactory,
		new \Base3\NeuronAi\Service\NeuronExecutionEventMapper(),
		$contextProfileService,
		$toolProfileService,
		new \Base3\NeuronAi\Service\NeuronContextInstructionsBuilder(),
		$suspensionRepository,
		$toolFactory
	);
	$sink = new \Base3\AssistantRuntime\Service\CollectingAgentEventSink();
	$result = $service->execute($request, $sink);

	$content = $result->getOutput()['assistant']['message']['content'] ?? null;
	$events = array_map(static fn($event): string => $event->getName(), $sink->getEvents());
	if ($content !== 'Tool result accepted') {
		throw new \RuntimeException('Unexpected content: ' . var_export($content, true));
	}
	if ($events !== ['msgid', 'tool.started', 'tool.finished', 'token', 'done']) {
		throw new \RuntimeException('Unexpected events: ' . json_encode($events));
	}

	$finishedEvent = $sink->getEvents()[2] ?? null;
	$finishedPayload = $finishedEvent?->getPayload() ?? [];
	if (($finishedPayload['execution']['status'] ?? null) !== 'success') {
		throw new \RuntimeException('Tool execution result was not mapped to the finished event.');
	}

	if (!is_string($provider->lastSystemPrompt) || !str_contains($provider->lastSystemPrompt, 'Current page is Smoke Test.')) {
		throw new \RuntimeException('Context profile was not added to Neuron instructions.');
	}

	if (count($provider->lastTools) !== 1 || $provider->lastTools[0]->getName() !== 'smoke_echo') {
		throw new \RuntimeException('Resolved BASE3 tool was not added to the Neuron agent.');
	}

	if (($toolSet->metadata['label'] ?? '') !== 'Smoke echo') {
		throw new \RuntimeException('Neuron tool label was not forwarded to the tool set.');
	}
	if (($toolSet->metadata['iteration'] ?? 0) !== 1 || ($toolSet->metadata['call_index'] ?? 0) !== 1) {
		throw new \RuntimeException('Neuron tool counters were not forwarded to the tool set.');
	}

	$toolEvents = array_values(array_filter(
		$sink->getEvents(),
		static fn($event): bool => in_array($event->getName(), ['tool.started', 'tool.finished'], true)
	));
	if (count($toolEvents) !== 2) {
		throw new \RuntimeException('Expected Neuron tool lifecycle events were not emitted.');
	}
	$startedPayload = $toolEvents[0]->getPayload();
	if (($startedPayload['label'] ?? '') !== 'Smoke echo' || ($startedPayload['call_index'] ?? 0) !== 1) {
		throw new \RuntimeException('Neuron tool activity payload is incomplete.');
	}

	$nativeTool = $toolFactory->create($toolSet)[0];
	$nativeTool->setCallId('smoke-call')->setInputs(['value' => 'Atlas'])->execute();
	$nativeResult = json_decode($nativeTool->getResult(), true);
	if (($nativeResult['echo'] ?? null) !== 'Atlas') {
		throw new \RuntimeException('Neuron tool adapter did not execute the BASE3 tool set.');
	}

	$failingToolSet = new class($capability) implements \Base3\AssistantFoundation\Api\IAgentToolSet {
		private \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog $catalog;
		public function __construct(\Base3\AssistantFoundation\Dto\AgentCapability $capability) {
			$this->catalog = new \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog([$capability]);
		}
		public function getCatalog(): \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog { return $this->catalog; }
		public function getWarnings(): array { return []; }
		public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): \Base3\AssistantFoundation\Dto\AgentToolResult {
			return \Base3\AssistantFoundation\Dto\AgentToolResult::failure($callId, $toolName, $arguments, 'smoke_failure', 'Expected failure');
		}
	};
	$failingTool = $toolFactory->create($failingToolSet)[0];
	$failingTool->setCallId('smoke-failure')->setInputs(['value' => 'Atlas'])->execute();
	$failureEvents = (new \Base3\NeuronAi\Service\NeuronExecutionEventMapper())->map(
		new \NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk($failingTool)
	);
	if (($failureEvents[0]->getName() ?? '') !== 'tool.failed') {
		throw new \RuntimeException('Failed BASE3 tool execution was not mapped to tool.failed.');
	}


	$provider->lastSystemPrompt = null;
	$provider->lastTools = [$nativeTool];
	$textTaskService = new \Base3\NeuronAi\Service\NeuronAgentTextTaskService(
		new \Base3\NeuronAi\Service\NeuronAgentFactory($providerFactory, $toolFactory),
		$contextProfileService,
		$toolProfileService,
		new \Base3\NeuronAi\Service\NeuronContextInstructionsBuilder()
	);
	$textTaskResult = $textTaskService->executeTextTask(new \Base3\AssistantFoundation\Dto\AgentTextTaskRequest(
		[
			'llm' => 'fake-llm',
			'context_profile' => 'smoke-context',
			'tool_profiles' => ['smoke-tools'],
			'neuron_mcp' => ['url' => 'https://must-not-be-used.invalid']
		],
		'chat-opening-message',
		'Create one concise greeting.',
		'Greet the user.',
		[
			'conversation_id' => 'must-not-be-used',
			'conversation_owner_key' => str_repeat('c', 64)
		],
		true,
		true
	));
	if ($textTaskResult->getContent() !== 'Hello world') {
		throw new \RuntimeException('Neuron isolated text task returned unexpected content.');
	}
	if (!is_string($provider->lastSystemPrompt) || !str_contains($provider->lastSystemPrompt, 'Current page is Smoke Test.')) {
		throw new \RuntimeException('Neuron isolated text task did not include the configured context profile.');
	}
	if (!str_contains($provider->lastSystemPrompt, 'smoke_echo')) {
		throw new \RuntimeException('Neuron isolated text task did not include the configured capability catalog.');
	}
	if ($provider->lastTools !== []) {
		throw new \RuntimeException('Neuron isolated text task exposed executable tools.');
	}
	if (($textTaskResult->getMetadata()['runtime'] ?? '') !== 'neuronai') {
		throw new \RuntimeException('Neuron isolated text task did not report its runtime.');
	}

	$modelProvider = new class implements \Base3\AssistantFoundation\Api\IAiModelConfigurationProvider {
		public static function getName(): string { return 'fakemodelconfigurationprovider'; }
		public function getOptions(): array { return []; }
		public function has(string $id): bool { return in_array($id, ['openai', 'compatible', 'mistral'], true); }
		public function get(string $id): \Base3\AssistantFoundation\Dto\AiModelConfiguration {
			return match ($id) {
				'openai' => new \Base3\AssistantFoundation\Dto\AiModelConfiguration('openai', 'OpenAI', 'openai-chat', 'model', 'https://api.openai.com/v1/chat/completions', 'fake-key'),
				'compatible' => new \Base3\AssistantFoundation\Dto\AiModelConfiguration('compatible', 'Compatible', 'openai-compatible-chat', 'model', 'https://example.invalid/api/v1/chat/completions', ''),
				'mistral' => new \Base3\AssistantFoundation\Dto\AiModelConfiguration('mistral', 'Mistral', 'mistral-chat', 'model', 'https://api.mistral.ai/v1/chat/completions', 'fake-key'),
				default => throw new \RuntimeException('Unknown fake model: ' . $id)
			};
		}
	};

	$runtimeProviderFactory = new \Base3\NeuronAi\Service\NeuronProviderFactory($modelProvider);
	$runtimeProviders = [];
	foreach (['openai', 'compatible', 'mistral'] as $llmId) {
		$configuration = \Base3\NeuronAi\Dto\NeuronAgentConfiguration::fromArrays(['llm' => $llmId], []);
		$runtimeProvider = $runtimeProviderFactory->create($configuration, $request);
		if (!$runtimeProvider instanceof \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface) {
			throw new \RuntimeException('Provider factory returned an invalid provider.');
		}
		$runtimeProviders[$llmId] = $runtimeProvider;
	}

	$recordingClient = new class implements \NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface {
		public ?\NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest $lastRequest = null;
		public function request(\NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest $request): \NeuronAi\Vendor\NeuronAI\HttpClient\HttpResponse {
			$this->lastRequest = $request;
			return new \NeuronAi\Vendor\NeuronAI\HttpClient\HttpResponse(200, '{}');
		}
		public function stream(\NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest $request): \NeuronAi\Vendor\NeuronAI\HttpClient\StreamInterface {
			throw new \LogicException('Stream is not used by the endpoint mapping smoke test.');
		}
		public function withBaseUri(string $baseUri): \NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface { return $this; }
		public function withHeaders(array $headers): \NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface { return $this; }
		public function withTimeout(float $timeout): \NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface { return $this; }
	};
	$endpointClient = new \Base3\NeuronAi\Http\ConfiguredEndpointHttpClient(
		'https://example.invalid/custom/chat/completions',
		$recordingClient
	);
	$endpointClient->withBaseUri('https://ignored.invalid/v1')->request(
		\NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest::post('chat/completions', ['model' => 'test'])
	);
	if ($recordingClient->lastRequest?->uri !== 'https://example.invalid/custom/chat/completions') {
		throw new \RuntimeException('Configured endpoint client did not preserve the exact endpoint.');
	}

	echo "NeuronAi smoke test OK.\n";
})($argv);
