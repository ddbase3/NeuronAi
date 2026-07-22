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

use AssistantFoundation\Dto\AgentExecutionRequest;
use NeuronAi\Api\INeuronAgentFactory;
use NeuronAi\Api\INeuronProviderFactory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Vendor\NeuronAI\Agent\Agent;
use NeuronAi\Vendor\NeuronAI\Agent\AgentInterface;
use NeuronAi\Vendor\NeuronAI\MCP\McpConnector;

final class NeuronAgentFactory implements INeuronAgentFactory {

	public function __construct(
		private readonly INeuronProviderFactory $providerFactory
	) {}

	public static function getName(): string {
		return 'neuronagentfactory';
	}

	public function create(
		NeuronAgentConfiguration $configuration,
		AgentExecutionRequest $request
	): AgentInterface {
		$agent = Agent::make()
			->setAiProvider($this->providerFactory->create($configuration, $request))
			->setInstructions($configuration->getInstructions());

		$agent->toolMaxRuns($configuration->getMaxToolRuns());
		$mcp = $configuration->getMcp();
		if ($mcp !== null) {
			$connector = McpConnector::make($mcp);
			$only = $this->normalizeStringList($mcp['only'] ?? []);
			$exclude = $this->normalizeStringList($mcp['exclude'] ?? []);
			if ($only !== []) {
				$connector->only($only);
			}
			if ($exclude !== []) {
				$connector->exclude($exclude);
			}
			$agent->addTool($connector->tools());
		}

		return $agent;
	}

	/** @return array<int,string> */
	private function normalizeStringList(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}
		$result = [];
		foreach ($value as $item) {
			if (!is_scalar($item) && $item !== null) {
				continue;
			}
			$item = trim((string)$item);
			if ($item !== '') {
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}
}
