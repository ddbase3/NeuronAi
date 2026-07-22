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

namespace NeuronAi\Dto;

use Base3\State\Api\IStateStore;
use NeuronAi\Chat\History\DatabaseNeuronChatHistory;
use Throwable;

/**
 * Holds a buffered chat history and its exclusive conversation lock for one
 * agent run.
 */
final class NeuronChatHistoryLease {

	private bool $released = false;
	private bool $completed = false;

	public function __construct(
		private readonly DatabaseNeuronChatHistory $history,
		private readonly IStateStore $stateStore,
		private readonly string $lockKey,
		private readonly string $lockToken
	) {}

	public function getHistory(): DatabaseNeuronChatHistory {
		return $this->history;
	}

	public function commit(): void {
		if ($this->completed) {
			return;
		}

		$this->history->commit();
		$this->completed = true;
	}

	public function discard(): void {
		if ($this->completed) {
			return;
		}

		$this->history->discard();
		$this->completed = true;
	}

	public function release(): void {
		if ($this->released) {
			return;
		}
		$this->released = true;

		try {
			$currentToken = $this->stateStore->get($this->lockKey);
			if (is_string($currentToken) && hash_equals($this->lockToken, $currentToken)) {
				$this->stateStore->delete($this->lockKey);
				$this->stateStore->flush();
			}
		}
		catch (Throwable) {
		}
	}

	public function __destruct() {
		if (!$this->completed) {
			$this->discard();
		}
		$this->release();
	}
}
