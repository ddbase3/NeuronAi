<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 *
 * NeuronAi integrates the Neuron AI agent runtime with AssistantFoundation.
 * It ships an isolated, reproducible Neuron AI runtime for ILIAS and
 * standalone BASE3 installations.
 **********************************************************************/

namespace NeuronAi\Service;

use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use NeuronAi\Tool\NeuronAgentTool;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;

final class NeuronAgentToolFactory {

	/** @return array<int,ToolInterface> */
	public function create(IAgentToolSet $toolSet): array {
		return array_map(
			fn(AgentCapability $capability): ToolInterface => $this->createOne($capability, $toolSet),
			$toolSet->getCatalog()->all()
		);
	}

	public function createOne(AgentCapability $capability, IAgentToolSet $toolSet): NeuronAgentTool {
		return new NeuronAgentTool($capability, $toolSet);
	}
}
