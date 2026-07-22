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
	if (!is_dir($foundationDir)) {
		fwrite(STDERR, "AssistantFoundation source directory not found. Pass it as first argument.\n");
		exit(1);
	}

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

		public function systemPrompt(?string $prompt): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
		public function setTools(array $tools): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
		public function messageMapper(): \NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface { throw new \LogicException('Not used by the smoke provider.'); }
		public function toolPayloadMapper(): \NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface { throw new \LogicException('Not used by the smoke provider.'); }
		public function chat(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message {
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Hello world');
		}
		public function stream(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \Generator {
			yield new \NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk('provider-message', 'Hello ');
			yield new \NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk('provider-message', 'world');
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Hello world');
		}
		public function structured(array|\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message $messages, string $class, array $response_schema): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message {
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Hello world');
		}
		public function setHttpClient(\NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface $client): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
	};

	$providerFactory = new class($provider) implements \NeuronAi\Api\INeuronProviderFactory {
		public function __construct(private readonly \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface $provider) {}
		public static function getName(): string { return 'fakeproviderfactory'; }
		public function create(\NeuronAi\Dto\NeuronAgentConfiguration $configuration, \AssistantFoundation\Dto\AgentExecutionRequest $request): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface {
			return $this->provider;
		}
	};

	$request = new \AssistantFoundation\Dto\AgentExecutionRequest([
		'llm' => 'fake-llm'
	], [
		'system' => 'Be concise.',
		'prompt' => 'Say hello.'
	]);
	$chatHistoryFactory = new class implements \NeuronAi\Api\INeuronChatHistoryFactory {
		public static function getName(): string { return 'nullneuronchathistoryfactory'; }
		public function create(
			\NeuronAi\Dto\NeuronAgentConfiguration $configuration,
			\AssistantFoundation\Dto\AgentExecutionRequest $request
		): ?\NeuronAi\Dto\NeuronChatHistoryLease {
			return null;
		}
	};
	$service = new \NeuronAi\Service\NeuronAgentExecutionService(
		new \NeuronAi\Service\NeuronAgentFactory($providerFactory),
		$chatHistoryFactory,
		new \NeuronAi\Service\NeuronExecutionEventMapper()
	);
	$sink = new \AssistantRuntime\Service\CollectingAgentEventSink();
	$result = $service->execute($request, $sink);

	$content = $result->getOutput()['assistant']['message']['content'] ?? null;
	$events = array_map(static fn($event): string => $event->getName(), $sink->getEvents());
	if ($content !== 'Hello world') {
		throw new \RuntimeException('Unexpected content: ' . var_export($content, true));
	}
	if ($events !== ['msgid', 'token', 'token', 'done']) {
		throw new \RuntimeException('Unexpected events: ' . json_encode($events));
	}

	$modelProvider = new class implements \AssistantFoundation\Api\IAiModelConfigurationProvider {
		public static function getName(): string { return 'fakemodelconfigurationprovider'; }
		public function getOptions(): array { return []; }
		public function has(string $id): bool { return in_array($id, ['openai', 'compatible', 'mistral'], true); }
		public function get(string $id): \AssistantFoundation\Dto\AiModelConfiguration {
			return match ($id) {
				'openai' => new \AssistantFoundation\Dto\AiModelConfiguration('openai', 'OpenAI', 'openai-chat', 'model', 'https://api.openai.com/v1/chat/completions', 'fake-key'),
				'compatible' => new \AssistantFoundation\Dto\AiModelConfiguration('compatible', 'Compatible', 'openai-compatible-chat', 'model', 'https://example.invalid/api/v1/chat/completions', ''),
				'mistral' => new \AssistantFoundation\Dto\AiModelConfiguration('mistral', 'Mistral', 'mistral-chat', 'model', 'https://api.mistral.ai/v1/chat/completions', 'fake-key'),
				default => throw new \RuntimeException('Unknown fake model: ' . $id)
			};
		}
	};

	$runtimeProviderFactory = new \NeuronAi\Service\NeuronProviderFactory($modelProvider);
	$runtimeProviders = [];
	foreach (['openai', 'compatible', 'mistral'] as $llmId) {
		$configuration = \NeuronAi\Dto\NeuronAgentConfiguration::fromArrays(['llm' => $llmId], []);
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
	$endpointClient = new \NeuronAi\Http\ConfiguredEndpointHttpClient(
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
