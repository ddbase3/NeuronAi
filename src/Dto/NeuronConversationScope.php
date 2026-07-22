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
 * Server-resolved persistence scope for one Neuron conversation.
 */
final class NeuronConversationScope {

	public function __construct(
		private readonly string $conversationKey,
		private readonly string $conversationId,
		private readonly string $ownerKey,
		private readonly string $configGroup,
		private readonly string $configName
	) {}

	public function getConversationKey(): string { return $this->conversationKey; }
	public function getConversationId(): string { return $this->conversationId; }
	public function getOwnerKey(): string { return $this->ownerKey; }
	public function getConfigGroup(): string { return $this->configGroup; }
	public function getConfigName(): string { return $this->configName; }
}
