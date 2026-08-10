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

use NeuronAi\Dto\NeuronAgentConfiguration;

/**
 * Canonical Neuron conversation-memory profile backed by the native history table.
 */
final class NeuronConversationMemoryProfile {

	public const DATABASE = NeuronAgentConfiguration::DEFAULT_MEMORY_PROFILE;

	public static function isSupported(string $profileId): bool {
		return trim($profileId) === self::DATABASE;
	}
}
