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

namespace NeuronAi\Api;

use AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\Api\IBase;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;

/**
 * Creates the provider for one execution.
 *
 * Host integrations may use the execution context for tenant- or user-specific
 * credentials while the default implementation only uses normalized settings.
 */
interface INeuronProviderFactory extends IBase {

	public function create(
		NeuronAgentConfiguration $configuration,
		AgentExecutionRequest $request
	): AIProviderInterface;
}
