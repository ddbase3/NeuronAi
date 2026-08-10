<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 **********************************************************************/

namespace NeuronAi\Service;

use AssistantFoundation\Api\IAgentConversationRuntimeService;
use AssistantFoundation\Dto\AgentConversation;
use AssistantFoundation\Dto\AgentConversationRequest;
use AssistantFoundation\Dto\AgentConversationState;
use NeuronAi\Dto\NeuronConversationScope;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;

/**
 * Runtime-neutral conversation access backed by the same Neuron-native history rows used during execution.
 */
final class NeuronAgentConversationService implements IAgentConversationRuntimeService {

	private const DEFAULT_NODE_ID = 'assistant';

	public function __construct(
		private readonly NeuronChatHistoryRepository $repository,
		private readonly NeuronConversationKeyFactory $conversationKeyFactory,
		private readonly NeuronConversationOwnerResolver $ownerResolver
	) {}

	public static function getName(): string {
		return 'neuronagentconversationservice';
	}

	public static function getRuntimeId(): string {
		return 'neuronai';
	}

	public function getState(AgentConversationRequest $request, string $conversationId = ''): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$conversations = $this->listConversations($ownerKey, $group, $name);
		$active = null;
		$messages = [];

		$conversationId = trim($conversationId);
		if ($conversationId !== '') {
			$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
			$row = $this->repository->touchConversation($scope);
			$active = $this->conversationFromRow($row);
			$messages = $this->visibleMessages($row);
			$conversations = $this->listConversations($ownerKey, $group, $name);
		}
		elseif ($conversations !== []) {
			$active = $conversations[0];
			$scope = $this->requireScope($active->getId(), $ownerKey, $group, $name);
			$row = $this->repository->touchConversation($scope);
			$active = $this->conversationFromRow($row);
			$messages = $this->visibleMessages($row);
			$conversations = $this->listConversations($ownerKey, $group, $name);
		}

		return new AgentConversationState(
			$conversations,
			$active,
			$messages,
			$this->resolveNodeId($request)
		);
	}

	public function createConversation(
		AgentConversationRequest $request,
		?string $conversationId = null,
		string $title = '',
		string $titleSource = AgentConversation::TITLE_SOURCE_TEMPORARY,
		string $openingMessage = ''
	): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$conversationId = $this->normalizeConversationId(
			$conversationId === null || trim($conversationId) === ''
				? 'conversation-' . bin2hex(random_bytes(20))
				: $conversationId
		);
		$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
		$title = $this->normalizeTitle($title);
		$titleSource = $this->normalizeTitleSource($titleSource);
		$this->repository->createConversation($scope, $title, $titleSource, $openingMessage);

		return $this->getState($request, $conversationId);
	}

	public function activateConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
		$this->repository->touchConversation($scope);

		return $this->getState($request, $conversationId);
	}

	public function renameConversation(
		AgentConversationRequest $request,
		string $conversationId,
		string $title,
		string $titleSource = AgentConversation::TITLE_SOURCE_MANUAL
	): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
		$this->repository->renameConversation(
			$scope,
			$this->normalizeTitle($title),
			$this->normalizeTitleSource($titleSource)
		);

		return $this->getState($request, $conversationId);
	}

	public function deleteConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
		$this->repository->deleteConversation($scope);

		return $this->getState($request);
	}

	public function appendMessage(
		AgentConversationRequest $request,
		string $conversationId,
		array $message
	): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
		$row = $this->repository->getConversation($scope);
		if ($row === null) {
			throw new \RuntimeException('Conversation not found: ' . $conversationId);
		}

		$messages = $this->decodeMessages($row);
		$messages[] = $this->serializeVisibleMessage($message);
		$this->repository->save(
			$scope->getConversationKey(),
			max(0, (int)($row['version'] ?? 0)),
			$messages,
			$this->decodeMessages($row)
		);
		$this->repository->touchConversation($scope);

		return $this->getState($request, $conversationId);
	}

	public function touchConversation(AgentConversationRequest $request, string $conversationId): AgentConversationState {
		$this->requireMemoryProfile($request);
		[$ownerKey, $group, $name] = $this->resolveChannel($request);
		$scope = $this->requireScope($conversationId, $ownerKey, $group, $name);
		$this->repository->touchConversation($scope);

		return $this->getState($request, $conversationId);
	}

	private function requireMemoryProfile(AgentConversationRequest $request): void {
		$configuration = $request->getAgentConfiguration();
		$profileId = strtolower(trim((string)($configuration['memory_profile'] ?? '')));
		if (!NeuronConversationMemoryProfile::isSupported($profileId)) {
			throw new \RuntimeException('Neuron AI conversation access requires the Neuron database memory profile.');
		}
	}

	/** @return array{0:string,1:string,2:string} */
	private function resolveChannel(AgentConversationRequest $request): array {
		$context = $request->getContext();
		$ownerKey = $this->ownerResolver->resolveOwnerKey();
		$group = substr(trim((string)($context['chatbot_config_group'] ?? '')), 0, 191);
		$name = substr(trim((string)($context['chatbot_config_name'] ?? '')), 0, 191);
		if ($group === '' || $name === '') {
			throw new \RuntimeException('Neuron AI conversation access requires chatbot configuration identity.');
		}

		return [$ownerKey, $group, $name];
	}

	private function requireScope(
		string $conversationId,
		string $ownerKey,
		string $group,
		string $name
	): NeuronConversationScope {
		$scope = $this->conversationKeyFactory->createFromValues(
			$this->normalizeConversationId($conversationId),
			$ownerKey,
			$group,
			$name
		);
		if (!$scope instanceof NeuronConversationScope) {
			throw new \RuntimeException('Neuron AI conversation scope could not be resolved.');
		}

		return $scope;
	}

	/** @return array<int,AgentConversation> */
	private function listConversations(string $ownerKey, string $group, string $name): array {
		$result = [];
		foreach ($this->repository->listConversations($ownerKey, $group, $name) as $row) {
			if (is_array($row)) {
				$result[] = $this->conversationFromRow($row);
			}
		}
		return $result;
	}

	/** @param array<string,mixed> $row */
	private function conversationFromRow(array $row): AgentConversation {
		return AgentConversation::fromArray([
			'id' => (string)($row['conversation_id'] ?? ''),
			'title' => $this->normalizeTitle((string)($row['title'] ?? '')),
			'title_source' => $this->normalizeTitleSource((string)($row['title_source'] ?? '')),
			'opening_message' => (string)($row['opening_message'] ?? ''),
			'created_at' => (string)($row['created_at'] ?? ''),
			'updated_at' => (string)($row['updated_at'] ?? ''),
			'last_active_at' => (string)($row['last_active_at'] ?? '')
		]);
	}

	/** @param array<string,mixed> $row @return array<int,array<string,mixed>> */
	private function visibleMessages(array $row): array {
		$result = [];
		foreach ($this->decodeMessages($row) as $message) {
			$role = strtolower(trim((string)($message['role'] ?? '')));
			$type = strtolower(trim((string)($message['type'] ?? '')));
			if (!in_array($role, ['user', 'assistant'], true) || $type === 'tool_call') {
				continue;
			}
			$content = $this->extractTextContent($message['content'] ?? null);
			if ($content === '') {
				continue;
			}
			$id = trim((string)($message['__id'] ?? ''));
			if ($id === '') {
				$id = 'msg_' . hash('sha256', $role . "\0" . $content . "\0" . count($result));
			}
			$result[] = [
				'id' => $id,
				'role' => $role,
				'content' => $content,
				'timestamp' => trim((string)($message['timestamp'] ?? '')),
				'feedback' => isset($message['feedback']) && is_scalar($message['feedback'])
					? (string)$message['feedback']
					: null
			];
		}

		return $result;
	}

	/** @param array<string,mixed> $row @return array<int,array<string,mixed>> */
	private function decodeMessages(array $row): array {
		$messages = json_decode((string)($row['messages'] ?? '[]'), true);
		if (!is_array($messages)) {
			throw new \RuntimeException('Neuron chat history contains invalid JSON.');
		}
		return array_values(array_filter($messages, 'is_array'));
	}

	/** @param array<string,mixed> $message @return array<string,mixed> */
	private function serializeVisibleMessage(array $message): array {
		$id = trim((string)($message['id'] ?? ''));
		$role = strtolower(trim((string)($message['role'] ?? '')));
		$content = trim((string)($message['content'] ?? ''));
		if ($id === '' || preg_match('/^[A-Za-z0-9._:-]+$/', $id) !== 1) {
			throw new \InvalidArgumentException('Conversation message requires a valid id.');
		}
		if (!in_array($role, ['user', 'assistant'], true)) {
			throw new \InvalidArgumentException('Conversation message contains an invalid role.');
		}
		if ($content === '') {
			throw new \InvalidArgumentException('Conversation message content must not be empty.');
		}

		$neuronMessage = $role === 'user'
			? new UserMessage($content)
			: new AssistantMessage($content);
		$neuronMessage->addMetadata('__id', $id);
		$timestamp = trim((string)($message['timestamp'] ?? ''));
		if ($timestamp !== '') {
			$neuronMessage->addMetadata('timestamp', $timestamp);
		}
		$feedback = $message['feedback'] ?? null;
		if (is_string($feedback) || $feedback === null) {
			$neuronMessage->addMetadata('feedback', $feedback);
		}

		return $neuronMessage->jsonSerialize();
	}

	private function extractTextContent(mixed $content): string {
		if (is_string($content)) {
			return trim($content);
		}
		if (!is_array($content)) {
			return '';
		}
		$parts = [];
		foreach ($content as $block) {
			if (!is_array($block)) {
				continue;
			}
			$type = strtolower(trim((string)($block['type'] ?? '')));
			if ($type !== '' && $type !== 'text') {
				continue;
			}
			$text = trim((string)($block['content'] ?? ''));
			if ($text !== '') {
				$parts[] = $text;
			}
		}
		return trim(implode("\n", $parts));
	}

	private function resolveNodeId(AgentConversationRequest $request): string {
		$nodeId = trim($request->getNodeId());
		return $nodeId !== '' ? $nodeId : self::DEFAULT_NODE_ID;
	}

	private function normalizeConversationId(string $conversationId): string {
		$conversationId = substr(trim($conversationId), 0, 100);
		$conversationId = preg_replace('/[^A-Za-z0-9._:-]+/', '', $conversationId) ?? '';
		if ($conversationId === '') {
			throw new \InvalidArgumentException('Conversation id is required.');
		}
		return $conversationId;
	}

	private function normalizeTitle(string $title): string {
		$title = trim($title);
		if ($title === '') {
			return 'New conversation';
		}
		return function_exists('mb_substr') ? mb_substr($title, 0, 255) : substr($title, 0, 255);
	}

	private function normalizeTitleSource(string $titleSource): string {
		$titleSource = strtolower(trim($titleSource));
		return in_array($titleSource, [
			AgentConversation::TITLE_SOURCE_TEMPORARY,
			AgentConversation::TITLE_SOURCE_AUTOMATIC,
			AgentConversation::TITLE_SOURCE_MANUAL
		], true) ? $titleSource : AgentConversation::TITLE_SOURCE_TEMPORARY;
	}
}
