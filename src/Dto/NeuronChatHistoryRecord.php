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

/**
 * Persisted Neuron history state loaded for one conversation.
 */
final class NeuronChatHistoryRecord {

	/** @param array<int,array<string,mixed>> $messages */
	public function __construct(
		private readonly array $messages,
		private readonly int $version
	) {}

	/** @return array<int,array<string,mixed>> */
	public function getMessages(): array { return $this->messages; }
	public function getVersion(): int { return $this->version; }
}
