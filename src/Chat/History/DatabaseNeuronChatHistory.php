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

namespace NeuronAi\Chat\History;

use NeuronAi\Dto\NeuronChatHistoryRecord;
use NeuronAi\Service\NeuronChatHistoryRepository;
use NeuronAi\Vendor\NeuronAI\Chat\History\AbstractChatHistory;

/**
 * Neuron-native chat history backed by the BASE3 database abstraction.
 *
 * Neuron may add several messages during one agent run. These changes remain
 * buffered until commit() is called after a complete assistant response. A
 * failed or cancelled run therefore cannot persist an incomplete user-only
 * history.
 */
final class DatabaseNeuronChatHistory extends AbstractChatHistory {

	private int $version;
	/** @var array<int,array<string,mixed>> */
	private array $persistedMessages;
	private bool $dirty = false;

	public function __construct(
		private readonly string $conversationKey,
		private readonly NeuronChatHistoryRepository $repository,
		NeuronChatHistoryRecord $record,
		int $contextWindow = 50000
	) {
		parent::__construct($contextWindow);
		$this->version = $record->getVersion();
		$this->persistedMessages = $record->getMessages();
		$this->history = $this->deserializeMessages($this->persistedMessages);
	}

	/** @param array<int,\NeuronAi\Vendor\NeuronAI\Chat\Messages\Message> $messages */
	protected function setMessages(array $messages): void {
		$this->dirty = true;
	}

	protected function clear(): void {
		$this->dirty = true;
	}

	public function commit(): void {
		if (!$this->dirty) {
			return;
		}

		$this->version = $this->repository->save(
			$this->conversationKey,
			$this->version,
			$this->history,
			$this->persistedMessages
		);
		$this->persistedMessages = $this->serializeCurrentMessages();
		$this->dirty = false;
	}

	public function discard(): void {
		$this->dirty = false;
	}

	/** @return array<int,array<string,mixed>> */
	private function serializeCurrentMessages(): array {
		$json = json_encode(
			$this->history,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
		);
		$messages = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		return is_array($messages) ? array_values($messages) : [];
	}
}
