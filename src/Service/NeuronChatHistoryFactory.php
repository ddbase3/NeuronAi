<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 *
 * NeuronAi integrates the Neuron AI agent runtime with AssistantFoundation.
 * It ships an isolated, reproducible Neuron AI runtime for ILIAS and
 * standalone BASE3 installations.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/neuronai
 * https://github.com/ddbase3/NeuronAi
 **********************************************************************/

namespace NeuronAi\Service;

use AssistantFoundation\Api\IAiModelConfigurationProvider;
use AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\State\Api\IStateStore;
use NeuronAi\Api\INeuronChatHistoryFactory;
use NeuronAi\Chat\History\DatabaseNeuronChatHistory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Dto\NeuronChatHistoryLease;
use Throwable;

/**
 * Creates locked, database-backed Neuron chat histories when a conversation
 * scope is present in the execution request.
 */
final class NeuronChatHistoryFactory implements INeuronChatHistoryFactory {

	private const DEFAULT_CONTEXT_WINDOW = 50000;
	private const LOCK_TTL_SECONDS = 900;
	private const LOCK_PREFIX = 'locks.neuronai.chathistory.';

	public function __construct(
		private readonly NeuronConversationKeyFactory $conversationKeyFactory,
		private readonly NeuronChatHistoryRepository $repository,
		private readonly IStateStore $stateStore,
		private readonly IAiModelConfigurationProvider $modelConfigurationProvider
	) {}

	public static function getName(): string {
		return 'neuronchathistoryfactory';
	}

	public function create(
		NeuronAgentConfiguration $configuration,
		AgentExecutionRequest $request
	): ?NeuronChatHistoryLease {
		$scope = $this->conversationKeyFactory->create($request);
		if ($scope === null) {
			return null;
		}

		$lockKey = self::LOCK_PREFIX . $scope->getConversationKey();
		$lockToken = bin2hex(random_bytes(16));
		if (!$this->stateStore->setIfNotExists($lockKey, $lockToken, self::LOCK_TTL_SECONDS)) {
			throw new \RuntimeException('Neuron AI conversation is already being processed.');
		}
		$this->stateStore->flush();

		try {
			$record = $this->repository->loadOrCreate($scope);
			$history = new DatabaseNeuronChatHistory(
				$scope->getConversationKey(),
				$this->repository,
				$record,
				$this->resolveContextWindow($configuration)
			);

			return new NeuronChatHistoryLease(
				$history,
				$this->stateStore,
				$lockKey,
				$lockToken
			);
		}
		catch (Throwable $exception) {
			try {
				$currentToken = $this->stateStore->get($lockKey);
				if (is_string($currentToken) && hash_equals($lockToken, $currentToken)) {
					$this->stateStore->delete($lockKey);
					$this->stateStore->flush();
				}
			}
			catch (Throwable) {
			}
			throw $exception;
		}
	}

	private function resolveContextWindow(NeuronAgentConfiguration $configuration): int {
		$model = $this->modelConfigurationProvider->get($configuration->getLlmId());
		$options = $model->getOptions();
		$value = $options['context_window'] ?? $options['contextWindow'] ?? self::DEFAULT_CONTEXT_WINDOW;
		$value = is_numeric($value) ? (int)$value : self::DEFAULT_CONTEXT_WINDOW;

		return $value > 0 ? $value : self::DEFAULT_CONTEXT_WINDOW;
	}
}
