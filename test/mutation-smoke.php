<?php declare(strict_types=1);

/**
 * CLI-only mutation approval smoke test.
 *
 * BASE3 may inspect PHP files while building its class map. Therefore this
 * executable test must remain inert unless it is invoked directly from CLI.
 */
if (
	PHP_SAPI !== 'cli'
	|| !isset($_SERVER['SCRIPT_FILENAME'])
	|| realpath((string)$_SERVER['SCRIPT_FILENAME']) !== __FILE__
) {
	return;
}

(static function(array $arguments): void {
	if (!interface_exists('Base3\\Api\\IBase')) {
		eval(<<<'PHP'
namespace Base3\Api;

interface IBase {
	public static function getName(): string;
}
PHP);
	}

	$pluginDir = dirname(__DIR__);
	$foundationDir = $arguments[1] ?? dirname($pluginDir) . '/AssistantFoundation/src';
	$runtimeDir = $arguments[2] ?? dirname($pluginDir) . '/AssistantRuntime/src';
	spl_autoload_register(static function(string $class) use ($pluginDir, $foundationDir, $runtimeDir): void {
		$prefixes = [
			'NeuronAi\\Vendor\\' => $pluginDir . '/src/Vendor/',
			'NeuronAi\\' => $pluginDir . '/src/',
			'AssistantFoundation\\' => $foundationDir . '/',
			'AssistantRuntime\\' => $runtimeDir . '/'
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
		public array $tools = [];
		public int $streamCalls = 0;
		public bool $receivedToolResult = false;
		public string $systemPrompt = '';

		public function systemPrompt(?string $prompt): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { $this->systemPrompt = (string)$prompt; return $this; }
		public function setTools(array $tools): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { $this->tools = $tools; return $this; }
		public function messageMapper(): \NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface { throw new \LogicException('Not used.'); }
		public function toolPayloadMapper(): \NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface { throw new \LogicException('Not used.'); }
		public function chat(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message { return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('done'); }
		public function stream(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \Generator {
			$this->streamCalls++;
			if ($this->streamCalls === 1) {
				if (!isset($this->tools[0])) {
					throw new \RuntimeException('Mutation smoke provider received no tool.');
				}
				$tool = clone $this->tools[0];
				$tool->setCallId('mutation-call')->setInputs(['value' => 'Atlas']);
				if (false) {
					yield null;
				}
				return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage(null, [$tool]);
			}

			$this->receivedToolResult = count(array_filter(
				$messages,
				static fn($message): bool => $message instanceof \NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolResultMessage
			)) === 1;
			yield new \NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk('mutation-final', 'Mutation confirmed');
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Mutation confirmed');
		}
		public function structured(array|\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message $messages, string $class, array $response_schema): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message { return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('done'); }
		public function setHttpClient(\NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface $client): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
	};

	$providerFactory = new class($provider) implements \NeuronAi\Api\INeuronProviderFactory {
		public function __construct(private readonly \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface $provider) {}
		public static function getName(): string { return 'mutationfakeproviderfactory'; }
		public function create(\NeuronAi\Dto\NeuronAgentConfiguration $configuration, \AssistantFoundation\Dto\AgentExecutionRequest $request): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this->provider; }
	};
	$chatHistoryFactory = new class implements \NeuronAi\Api\INeuronChatHistoryFactory {
		public static function getName(): string { return 'mutationnullhistoryfactory'; }
		public function create(\NeuronAi\Dto\NeuronAgentConfiguration $configuration, \AssistantFoundation\Dto\AgentExecutionRequest $request): ?\NeuronAi\Dto\NeuronChatHistoryLease { return null; }
	};
	$contextProfileService = new class implements \AssistantFoundation\Api\IAgentContextProfileService {
		public static function getName(): string { return 'mutationemptycontextservice'; }
		public function getOptions(): array { return []; }
		public function hasProfile(string $profileId): bool { return false; }
		public function build(string $profileId, \AssistantFoundation\Dto\AgentExecutionRequest $request): \AssistantFoundation\Dto\AgentContextProfileResult { return new \AssistantFoundation\Dto\AgentContextProfileResult(''); }
	};

	$capability = new \AssistantFoundation\Dto\AgentCapability(
		'smoke_mutation',
		'Smoke mutation',
		'Writes one controlled smoke value.',
		'test',
		['smoke', 'mutation'],
		0,
		[
			'type' => 'function',
			'readOnlyHint' => false,
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => false,
			'function' => [
				'name' => 'smoke_mutation',
				'description' => 'Writes one controlled smoke value.',
				'parameters' => [
					'type' => 'object',
					'properties' => ['value' => ['type' => 'string']],
					'required' => ['value']
				]
			]
		]
	);
	$toolSet = new class($capability) implements \AssistantFoundation\Api\IAgentConfirmableToolSet {
		private \AssistantFoundation\Dto\AgentCapabilityCatalog $catalog;
		public int $executions = 0;
		public ?\AssistantFoundation\Dto\AgentInteractionResponse $lastResponse = null;
		public function __construct(\AssistantFoundation\Dto\AgentCapability $capability) { $this->catalog = new \AssistantFoundation\Dto\AgentCapabilityCatalog([$capability]); }
		public function getCatalog(): \AssistantFoundation\Dto\AgentCapabilityCatalog { return $this->catalog; }
		public function getWarnings(): array { return []; }
		public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): \AssistantFoundation\Dto\AgentToolResult { throw new \LogicException('Mutation must not execute without approval.'); }
		public function prepareSuspension(string $callId, string $toolName, array $arguments, array $metadata = []): ?\AssistantFoundation\Dto\AgentSuspension {
			$action = new \AssistantFoundation\Dto\AgentAction($callId, \AssistantFoundation\Dto\AgentAction::TYPE_TOOL_CALL, $toolName, $arguments);
			$request = new \AssistantFoundation\Dto\AgentInteractionRequest(
				'mutation-request',
				\AssistantFoundation\Dto\AgentInteractionRequest::KIND_APPROVAL,
				$action,
				str_repeat('a', 64),
				'Confirm smoke mutation',
				'Execute the controlled mutation?',
				['Value' => $arguments['value'] ?? ''],
				'medium'
			);
			return new \AssistantFoundation\Dto\AgentSuspension(
				'mutation-suspension',
				\AssistantFoundation\Dto\AgentExecutionStatus::AWAITING_APPROVAL,
				[$request],
				['tool_call' => [
					'id' => $callId,
					'name' => $toolName,
					'arguments' => $arguments,
					'metadata' => $metadata
				]],
				gmdate('c')
			);
		}
		public function resumeSuspension(\AssistantFoundation\Dto\AgentSuspension $suspension, \AssistantFoundation\Dto\AgentInteractionResponse $response, array $metadata = []): \AssistantFoundation\Dto\AgentToolResult {
			$this->lastResponse = $response;
			if ($response->getDecision() !== \AssistantFoundation\Dto\AgentInteractionResponse::DECISION_APPROVE) {
				return \AssistantFoundation\Dto\AgentToolResult::failure('mutation-call', 'smoke_mutation', ['value' => 'Atlas'], 'denied', 'Denied');
			}
			$this->executions++;
			return \AssistantFoundation\Dto\AgentToolResult::success('mutation-call', 'smoke_mutation', ['value' => 'Atlas'], ['stored' => 'Atlas'], ['iteration' => 1, 'call_index' => 1]);
		}
	};
	$unsafeToolSet = new class($capability) implements \AssistantFoundation\Api\IAgentToolSet {
		private \AssistantFoundation\Dto\AgentCapabilityCatalog $catalog;
		public function __construct(\AssistantFoundation\Dto\AgentCapability $capability) { $this->catalog = new \AssistantFoundation\Dto\AgentCapabilityCatalog([$capability]); }
		public function getCatalog(): \AssistantFoundation\Dto\AgentCapabilityCatalog { return $this->catalog; }
		public function getWarnings(): array { return []; }
		public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): \AssistantFoundation\Dto\AgentToolResult { return \AssistantFoundation\Dto\AgentToolResult::success($callId, $toolName, $arguments, ['unsafe' => true]); }
	};
	$unsafeTool = (new \NeuronAi\Service\NeuronAgentToolFactory())->createOne($capability, $unsafeToolSet);
	$unsafeTool->setCallId('unsafe-mutation')->setInputs(['value' => 'Atlas']);
	try {
		$unsafeTool->execute();
		throw new \RuntimeException('Mutation tool executed without a confirmable tool set.');
	}
	catch (\RuntimeException $e) {
		if (!str_contains($e->getMessage(), 'cannot suspend execution')) {
			throw $e;
		}
	}

	$toolProfileService = new class($toolSet) implements \AssistantFoundation\Api\IAgentToolProfileService {
		public function __construct(private readonly \AssistantFoundation\Api\IAgentToolSet $toolSet) {}
		public static function getName(): string { return 'mutationtoolprofileservice'; }
		public function getOptions(): array { return [['id' => 'mutation-tools', 'label' => 'Mutation tools']]; }
		public function hasProfile(string $profileId): bool { return $profileId === 'mutation-tools'; }
		public function resolve(array $profileIds, \AssistantFoundation\Dto\AgentExecutionRequest $request): \AssistantFoundation\Api\IAgentToolSet { return new \AssistantRuntime\Service\CompositeAgentToolSet([$this->toolSet]); }
	};
	$repository = new class implements \AssistantFoundation\Api\IAgentSuspensionRepository {
		private array $records = [];
		private array $claims = [];
		public function create(\AssistantFoundation\Dto\AgentSuspension $suspension, int $ttlSeconds): string {
			$handle = str_repeat('h', 43);
			$this->records[$handle] = $suspension;
			return $handle;
		}
		public function claim(string $resumeHandle): \AssistantFoundation\Dto\AgentSuspensionClaim {
			if (!isset($this->records[$resumeHandle])) {
				throw new \RuntimeException('Missing smoke suspension.');
			}
			$claim = new \AssistantFoundation\Dto\AgentSuspensionClaim($resumeHandle, 'smoke-claim', $this->records[$resumeHandle]);
			$this->claims[$resumeHandle] = $claim;
			return $claim;
		}
		public function release(\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void { unset($this->claims[$claim->getResumeHandle()]); }
		public function consume(\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void { unset($this->claims[$claim->getResumeHandle()], $this->records[$claim->getResumeHandle()]); }
	};

	$toolFactory = new \NeuronAi\Service\NeuronAgentToolFactory();
	$service = new \NeuronAi\Service\NeuronAgentExecutionService(
		new \NeuronAi\Service\NeuronAgentFactory($providerFactory, $toolFactory),
		$chatHistoryFactory,
		new \NeuronAi\Service\NeuronExecutionEventMapper(),
		$contextProfileService,
		$toolProfileService,
		new \NeuronAi\Service\NeuronContextInstructionsBuilder(),
		$repository,
		$toolFactory
	);
	$configuration = ['llm' => 'fake', 'tool_profiles' => ['mutation-tools']];
	$context = [
		'config_group' => 'copg-chatbot',
		'config_name' => 'mutation-smoke',
		'conversation_id' => 'mutation-conversation',
		'conversation_owner_key' => str_repeat('b', 64)
	];
	$firstSink = new \AssistantRuntime\Service\CollectingAgentEventSink();
	$first = $service->execute(new \AssistantFoundation\Dto\AgentExecutionRequest(
		$configuration,
		['prompt' => 'Store Atlas.'],
		$context
	), $firstSink);
	if (!$first->getAgentResult()?->isSuspended()) {
		throw new \RuntimeException('Mutation smoke run did not suspend.');
	}
	if (
		!str_contains($provider->systemPrompt, '`smoke_mutation`')
		|| !str_contains($provider->systemPrompt, 'do not ask for confirmation in natural language')
	) {
		throw new \RuntimeException('Mutation tool approval guidelines were not added to the Neuron system prompt.');
	}
	$suspensionState = $first->getAgentResult()->getState()->getSuspension();
	$handle = $suspensionState?->getResumeHandle() ?? '';
	$requestId = $suspensionState?->getInteractionRequests()[0]->getId() ?? '';
	if ($handle === '' || $requestId === '' || $toolSet->executions !== 0) {
		throw new \RuntimeException('Mutation executed before approval or suspension metadata is incomplete.');
	}

	$secondSink = new \AssistantRuntime\Service\CollectingAgentEventSink();
	$second = $service->execute(new \AssistantFoundation\Dto\AgentExecutionRequest(
		$configuration,
		[
			'prompt' => 'I approve.',
			'resume' => [
				'resume_handle' => $handle,
				'responses' => [[
					'request_id' => $requestId,
					'decision' => 'approve'
				]]
			]
		],
		$context
	), $secondSink);
	$content = $second->getOutput()['assistant']['message']['content'] ?? '';
	if ($content !== 'Mutation confirmed' || $toolSet->executions !== 1 || !$provider->receivedToolResult) {
		throw new \RuntimeException('Approved mutation did not resume exactly once through Neuron.');
	}
	$eventNames = array_map(static fn($event): string => $event->getName(), $secondSink->getEvents());
	if ($eventNames !== ['msgid', 'tool.started', 'tool.finished', 'token', 'done']) {
		throw new \RuntimeException('Unexpected resumed mutation events: ' . json_encode($eventNames));
	}

	$provider->streamCalls = 0;
	$provider->receivedToolResult = false;
	$feedbackStart = $service->execute(new \AssistantFoundation\Dto\AgentExecutionRequest(
		$configuration,
		['prompt' => 'Store Atlas again.'],
		$context
	), new \AssistantRuntime\Service\CollectingAgentEventSink());
	$feedbackSuspension = $feedbackStart->getAgentResult()?->getState()->getSuspension();
	$feedbackHandle = $feedbackSuspension?->getResumeHandle() ?? '';
	if ($feedbackHandle === '') {
		throw new \RuntimeException('Feedback smoke run did not create a new suspension.');
	}

	$feedbackText = 'Bitte speichere stattdessen den Wert Orion.';
	$feedbackResult = $service->execute(new \AssistantFoundation\Dto\AgentExecutionRequest(
		$configuration,
		[
			'prompt' => $feedbackText,
			'resume' => [
				'resume_handle' => $feedbackHandle,
				'response_text' => $feedbackText
			]
		],
		$context
	), new \AssistantRuntime\Service\CollectingAgentEventSink());
	if (
		$toolSet->executions !== 1
		|| $toolSet->lastResponse?->getDecision() !== \AssistantFoundation\Dto\AgentInteractionResponse::DECISION_DENY
		|| $toolSet->lastResponse?->getNote() !== $feedbackText
		|| ($feedbackResult->getOutput()['assistant']['message']['content'] ?? '') !== 'Mutation confirmed'
	) {
		throw new \RuntimeException('Free-text approval feedback did not safely deny the pending mutation.');
	}

	echo "NeuronAi mutation smoke test OK.\n";
})($argv);
