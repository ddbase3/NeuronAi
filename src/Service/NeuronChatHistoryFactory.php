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
use NeuronAi\Api\INeuronChatHistoryFactory;
use NeuronAi\Chat\History\DatabaseNeuronChatHistory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Dto\NeuronChatHistoryLease;

/**
 * Creates database-backed Neuron chat histories when a conversation scope is
 * present in the execution request.
 *
 * The canonical history row owns concurrency through optimistic versioning.
 */
final class NeuronChatHistoryFactory implements INeuronChatHistoryFactory {

	private const DEFAULT_CONTEXT_WINDOW = 50000;

	public function __construct(
		private readonly NeuronConversationKeyFactory $conversationKeyFactory,
		private readonly NeuronConversationOwnerResolver $ownerResolver,
		private readonly NeuronChatHistoryRepository $repository,
		private readonly IAiModelConfigurationProvider $modelConfigurationProvider
	) {}

	public static function getName(): string {
		return 'neuronchathistoryfactory';
	}

	public function create(
		NeuronAgentConfiguration $configuration,
		AgentExecutionRequest $request
	): ?NeuronChatHistoryLease {
		if (!NeuronConversationMemoryProfile::isSupported($configuration->getMemoryProfile())) {
			return null;
		}

		$scope = $this->conversationKeyFactory->create(
			$request,
			$this->ownerResolver->resolveOwnerKey()
		);
		if ($scope === null) {
			return null;
		}

		$record = $this->repository->loadOrCreate($scope);
		$history = new DatabaseNeuronChatHistory(
			$scope->getConversationKey(),
			$this->repository,
			$record,
			$this->resolveContextWindow($configuration)
		);

		return new NeuronChatHistoryLease($history);
	}

	private function resolveContextWindow(NeuronAgentConfiguration $configuration): int {
		$model = $this->modelConfigurationProvider->get($configuration->getLlmId());
		$options = $model->getOptions();
		$value = $options['context_window'] ?? $options['contextWindow'] ?? self::DEFAULT_CONTEXT_WINDOW;
		$value = is_numeric($value) ? (int)$value : self::DEFAULT_CONTEXT_WINDOW;

		return $value > 0 ? $value : self::DEFAULT_CONTEXT_WINDOW;
	}
}
