<?php declare(strict_types=1);

/**
 * CLI-only persistent conversation smoke test.
 *
 * Verifies that the canonical Neuron history row can be opened repeatedly
 * without a second runtime lock and that a read-only execution can discard
 * temporary messages without changing the persisted conversation.
 */
if (
	PHP_SAPI !== 'cli'
	|| !isset($_SERVER['SCRIPT_FILENAME'])
	|| realpath((string)$_SERVER['SCRIPT_FILENAME']) !== __FILE__
) {
	return;
}

(static function(array $arguments): void {
	if (!interface_exists('Base3\\CoreFoundation\\Api\\IBase')) {
		eval(<<<'PHP'
namespace Base3\CoreFoundation\Api;
interface IBase { public static function getName(): string; }
PHP);
	}
	if (!interface_exists('Base3\\CoreFoundation\\Database\\Api\\IDatabase')) {
		eval(<<<'PHP'
namespace Base3\CoreFoundation\Database\Api;
interface IDatabase {
	public function connect(): void;
	public function connected(): bool;
	public function disconnect(): void;
	public function beginTransaction(): void;
	public function commit(): void;
	public function rollback(): void;
	public function nonQuery(string $query): void;
	public function scalarQuery(string $query): mixed;
	public function singleQuery(string $query): ?array;
	public function &listQuery(string $query): array;
	public function &multiQuery(string $query): array;
	public function affectedRows(): int;
	public function insertId(): int|string;
	public function escape(string $str): string;
	public function isError(): bool;
	public function errorNumber(): int;
	public function errorMessage(): string;
}
PHP);
	}
	if (!interface_exists('Base3\\CoreFoundation\\Accesscontrol\\Api\\IAccesscontrol')) {
		eval(<<<'PHP'
namespace Base3\CoreFoundation\Accesscontrol\Api;
interface IAccesscontrol {
	public function getUserId();
	public function authenticate(): void;
}
PHP);
	}
	if (!interface_exists('Base3\\CoreFoundation\\Session\\Api\\ISession')) {
		eval(<<<'PHP'
namespace Base3\CoreFoundation\Session\Api;
interface ISession {
	public function started(): bool;
	public function getId(): string;
	public function start(): bool;
	public function destroy(): bool;
	public function get(string $key, mixed $default = null): mixed;
	public function set(string $key, mixed $value): void;
	public function has(string $key): bool;
	public function remove(string $key): void;
}
PHP);
	}

	$pluginDir = dirname(__DIR__);
	$foundationDir = $arguments[1] ?? dirname($pluginDir) . '/AssistantFoundation/src';
	spl_autoload_register(static function(string $class) use ($pluginDir, $foundationDir): void {
		$prefixes = [
			'NeuronAi\\Vendor\\' => $pluginDir . '/src/Vendor/',
			'Base3\\NeuronAi\\' => $pluginDir . '/src/',
			'Base3\\AssistantFoundation\\' => $foundationDir . '/'
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

	$database = new class implements \Base3\CoreFoundation\Database\Api\IDatabase {
		private ?array $row = null;
		private int $affectedRows = 0;
		private bool $connected = false;

		public function connect(): void { $this->connected = true; }
		public function connected(): bool { return $this->connected; }
		public function disconnect(): void { $this->connected = false; }
		public function beginTransaction(): void {}
		public function commit(): void {}
		public function rollback(): void {}

		public function nonQuery(string $query): void {
			$this->affectedRows = 0;
			$query = trim($query);
			if (str_starts_with($query, 'CREATE TABLE IF NOT EXISTS')) {
				return;
			}
			if (str_starts_with($query, 'INSERT INTO')) {
				if ($this->row === null) {
					$this->row = ['messages' => '[]', 'version' => 0];
				}
				$this->affectedRows = 1;
				return;
			}
			if (!str_starts_with($query, 'UPDATE') || $this->row === null) {
				return;
			}
			if (preg_match('/AND `version` = (\d+)/', $query, $versionMatch) !== 1) {
				return;
			}
			if ((int)$versionMatch[1] !== (int)$this->row['version']) {
				return;
			}
			if (preg_match('/`messages` = \'((?:\\\\.|[^\'])*)\'/s', $query, $messageMatch) !== 1) {
				return;
			}
			$this->row['messages'] = stripslashes($messageMatch[1]);
			$this->row['version'] = (int)$this->row['version'] + 1;
			$this->affectedRows = 1;
		}

		public function scalarQuery(string $query): mixed { return null; }
		public function singleQuery(string $query): ?array { return $this->row; }
		public function &listQuery(string $query): array { $result = []; return $result; }
		public function &multiQuery(string $query): array { $result = []; return $result; }
		public function affectedRows(): int { return $this->affectedRows; }
		public function insertId(): int|string { return 1; }
		public function escape(string $str): string { return addslashes($str); }
		public function isError(): bool { return false; }
		public function errorNumber(): int { return 0; }
		public function errorMessage(): string { return ''; }
	};

	$accesscontrol = new class implements \Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol {
		public function getUserId() { return 42; }
		public function authenticate(): void {}
	};
	$session = new class implements \Base3\CoreFoundation\Session\Api\ISession {
		private array $values = [];
		public function started(): bool { return true; }
		public function getId(): string { return 'conversation-smoke-session'; }
		public function start(): bool { return true; }
		public function destroy(): bool { return true; }
		public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
		public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
		public function has(string $key): bool { return array_key_exists($key, $this->values); }
		public function remove(string $key): void { unset($this->values[$key]); }
	};
	$modelProvider = new class implements \Base3\AssistantFoundation\Api\IAiModelConfigurationProvider {
		public static function getName(): string { return 'conversation-smoke-models'; }
		public function getOptions(): array { return []; }
		public function has(string $id): bool { return $id === 'fake-llm'; }
		public function get(string $id): \Base3\AssistantFoundation\Dto\AiModelConfiguration {
			return new \Base3\AssistantFoundation\Dto\AiModelConfiguration(
				'fake-llm',
				'Fake LLM',
				'openai-chat',
				'fake-model',
				'https://example.invalid',
				'',
				['context_window' => 4096]
			);
		}
	};

	$repository = new \Base3\NeuronAi\Service\NeuronChatHistoryRepository($database);
	$factory = new \Base3\NeuronAi\Service\NeuronChatHistoryFactory(
		new \Base3\NeuronAi\Service\NeuronConversationKeyFactory(),
		new \Base3\NeuronAi\Service\NeuronConversationOwnerResolver($accesscontrol, $session),
		$repository,
		$modelProvider
	);
	$configuration = \Base3\NeuronAi\Dto\NeuronAgentConfiguration::fromArrays([
		'llm' => 'fake-llm',
		'memory_profile' => \Base3\NeuronAi\Dto\NeuronAgentConfiguration::DEFAULT_MEMORY_PROFILE
	], []);
	$request = new \Base3\AssistantFoundation\Dto\AgentExecutionRequest(
		['llm' => 'fake-llm'],
		['prompt' => 'Hello', 'mode' => 'chat'],
		[
			'conversation_id' => 'conversation-smoke',
			'chatbot_config_group' => 'copg-chatbot',
			'chatbot_config_name' => 'conversation-smoke'
		]
	);

	$first = $factory->create($configuration, $request);
	if (!$first instanceof \Base3\NeuronAi\Dto\NeuronChatHistoryLease) {
		throw new \RuntimeException('Conversation smoke test did not create persistent history.');
	}
	$first->getHistory()->addMessage(new \NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage('Existing question'));
	$first->getHistory()->addMessage(new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('Existing answer'));
	$first->commit();

	$second = $factory->create($configuration, $request);
	if (!$second instanceof \Base3\NeuronAi\Dto\NeuronChatHistoryLease) {
		throw new \RuntimeException('Conversation could not be opened a second time.');
	}
	if (count($second->getHistory()->getMessages()) !== 2) {
		throw new \RuntimeException('Persisted conversation was not reloaded.');
	}
	$second->discard();

	$provider = new class implements \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface {
		public array $messages = [];
		public array $tools = [];
		public function systemPrompt(?string $prompt): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
		public function setTools(array $tools): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { $this->tools = $tools; return $this; }
		public function messageMapper(): \NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface { throw new \LogicException('Not used.'); }
		public function toolPayloadMapper(): \NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface { throw new \LogicException('Not used.'); }
		public function chat(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message { return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('["Suggestion"]'); }
		public function stream(\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message ...$messages): \Generator {
			$this->messages = $messages;
			yield new \NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk('suggestion-message', '["Suggestion"]');
			return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('["Suggestion"]');
		}
		public function structured(array|\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message $messages, string $class, array $response_schema): \NeuronAi\Vendor\NeuronAI\Chat\Messages\Message { return new \NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage('["Suggestion"]'); }
		public function setHttpClient(\NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface $client): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this; }
	};
	$providerFactory = new class($provider) implements \Base3\NeuronAi\Api\INeuronProviderFactory {
		public function __construct(private readonly \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface $provider) {}
		public static function getName(): string { return 'conversation-smoke-provider'; }
		public function create(\Base3\NeuronAi\Dto\NeuronAgentConfiguration $configuration, \Base3\AssistantFoundation\Dto\AgentExecutionRequest $request): \NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface { return $this->provider; }
	};
	$contextProfiles = new class implements \Base3\AssistantFoundation\Api\IAgentContextProfileService {
		public static function getName(): string { return 'conversation-smoke-context'; }
		public function getOptions(): array { return []; }
		public function hasProfile(string $profileId): bool { return false; }
		public function build(string $profileId, \Base3\AssistantFoundation\Dto\AgentExecutionRequest $request): \Base3\AssistantFoundation\Dto\AgentContextProfileResult { return new \Base3\AssistantFoundation\Dto\AgentContextProfileResult(''); }
	};
	$emptyToolSet = new class implements \Base3\AssistantFoundation\Api\IAgentToolSet {
		private \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog $catalog;
		public function __construct() { $this->catalog = new \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog([]); }
		public function getCatalog(): \Base3\AssistantFoundation\Dto\AgentCapabilityCatalog { return $this->catalog; }
		public function getWarnings(): array { return []; }
		public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): \Base3\AssistantFoundation\Dto\AgentToolResult { throw new \LogicException('Suggestion turns must not execute tools.'); }
	};
	$toolProfiles = new class($emptyToolSet) implements \Base3\AssistantFoundation\Api\IAgentToolProfileService {
		public function __construct(private readonly \Base3\AssistantFoundation\Api\IAgentToolSet $toolSet) {}
		public static function getName(): string { return 'conversation-smoke-tools'; }
		public function getOptions(): array { return []; }
		public function hasProfile(string $profileId): bool { return false; }
		public function resolve(array $profileIds, \Base3\AssistantFoundation\Dto\AgentExecutionRequest $request): \Base3\AssistantFoundation\Api\IAgentToolSet { return $this->toolSet; }
	};
	$suspensions = new class implements \Base3\AssistantFoundation\Api\IAgentSuspensionRepository {
		public function create(\Base3\AssistantFoundation\Dto\AgentSuspension $suspension, int $ttlSeconds): string { throw new \LogicException('Suggestion turns must not suspend.'); }
		public function findPending(string $scopeId): ?\Base3\AssistantFoundation\Dto\AgentSuspensionState { return null; }
		public function claim(string $resumeHandle): \Base3\AssistantFoundation\Dto\AgentSuspensionClaim { throw new \LogicException('Suggestion turns do not resume.'); }
		public function release(\Base3\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
		public function consume(\Base3\AssistantFoundation\Dto\AgentSuspensionClaim $claim): void {}
	};
	$toolFactory = new \Base3\NeuronAi\Service\NeuronAgentToolFactory();
	$executionService = new \Base3\NeuronAi\Service\NeuronAgentExecutionService(
		new \Base3\NeuronAi\Service\NeuronAgentFactory($providerFactory, $toolFactory),
		$factory,
		new \Base3\NeuronAi\Service\NeuronExecutionEventMapper(),
		$contextProfiles,
		$toolProfiles,
		new \Base3\NeuronAi\Service\NeuronContextInstructionsBuilder(),
		$suspensions,
		$toolFactory
	);
	$suggestionRequest = new \Base3\AssistantFoundation\Dto\AgentExecutionRequest(
		[
			'llm' => 'fake-llm',
			'memory_profile' => \Base3\NeuronAi\Dto\NeuronAgentConfiguration::DEFAULT_MEMORY_PROFILE,
			'tool_profiles' => ['must-not-run']
		],
		['prompt' => 'Generate suggestions.', 'mode' => 'suggestions'],
		$request->getContext()
	);
	$suggestionResult = $executionService->execute($suggestionRequest);
	if (($suggestionResult->getOutput()['assistant']['message']['content'] ?? '') !== '["Suggestion"]') {
		throw new \RuntimeException('Suggestion execution returned unexpected content.');
	}
	if ($provider->tools !== []) {
		throw new \RuntimeException('Suggestion execution exposed executable tools.');
	}
	$seen = array_map(static fn($message): string => (string)$message->getContent(), $provider->messages);
	if (!in_array('Existing question', $seen, true) || !in_array('Existing answer', $seen, true)) {
		throw new \RuntimeException('Suggestion execution did not read the active conversation.');
	}

	$third = $factory->create($configuration, $request);
	if (!$third instanceof \Base3\NeuronAi\Dto\NeuronChatHistoryLease) {
		throw new \RuntimeException('Conversation could not be reopened after a read-only turn.');
	}
	$messages = $third->getHistory()->getMessages();
	if (
		count($messages) !== 2
		|| $messages[0]->getContent() !== 'Existing question'
		|| $messages[1]->getContent() !== 'Existing answer'
	) {
		throw new \RuntimeException('Read-only conversation turn changed persisted history.');
	}
	$third->discard();

	echo "NeuronAi conversation smoke test OK.\n";
})($argv);
