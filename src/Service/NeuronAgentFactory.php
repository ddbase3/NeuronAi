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

use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentExecutionRequest;
use NeuronAi\Api\INeuronAgentFactory;
use NeuronAi\Api\INeuronProviderFactory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Vendor\NeuronAI\Agent\Agent;
use NeuronAi\Vendor\NeuronAI\Agent\AgentInterface;
use NeuronAi\Vendor\NeuronAI\MCP\McpConnector;

final class NeuronAgentFactory implements INeuronAgentFactory {

	public function __construct(
		private readonly INeuronProviderFactory $providerFactory,
		private readonly NeuronAgentToolFactory $toolFactory
	) {}

	public static function getName(): string {
		return 'neuronagentfactory';
	}

	public function create(
		NeuronAgentConfiguration $configuration,
		AgentExecutionRequest $request,
		?IAgentToolSet $toolSet = null
	): AgentInterface {
		$instructions = $this->buildInstructions($configuration->getInstructions(), $toolSet);
		$agent = Agent::make()
			->setAiProvider($this->providerFactory->create($configuration, $request))
			->setInstructions($instructions);

		$agent->toolMaxRuns($configuration->getMaxToolRuns());
		if ($toolSet !== null && $toolSet->getCatalog()->count() > 0) {
			$agent->addTool($this->toolFactory->create($toolSet));
		}

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

	private function buildInstructions(string $instructions, ?IAgentToolSet $toolSet): string {
		$instructions = trim($instructions);
		if ($toolSet === null || $toolSet->getCatalog()->count() === 0) {
			return $instructions;
		}

		$approvalTools = [];
		foreach ($toolSet->getCatalog()->all() as $capability) {
			if (!$capability instanceof AgentCapability || !$this->requiresApproval($capability)) {
				continue;
			}

			$label = trim($capability->getTitle());
			$description = trim($capability->getDescription());
			$line = '- `' . $capability->getName() . '`';
			if ($label !== '' && $label !== $capability->getName()) {
				$line .= ' (' . $label . ')';
			}
			if ($description !== '') {
				$line .= ': ' . $description;
			}
			$approvalTools[] = $line;
		}

		$guidelines = [
			'<BASE3-TOOL-GUIDELINES>',
			'Use only tool names that are actually registered for this turn. Never invent, translate, alias or guess a tool name.',
			'When the user explicitly requests an action and a matching registered tool exists, call that exact tool immediately with the required arguments.',
			'For tools that require approval, do not ask for confirmation in natural language. Call the tool once. The host application will pause execution and display physical approval and cancel buttons to the user.',
			'Do not claim that a mutation was completed before the tool returns a successful result.',
			'If no registered tool can perform the requested action, say so clearly instead of fabricating a tool call.'
		];

		if ($approvalTools !== []) {
			$guidelines[] = 'Registered approval-bound tools for this turn:';
			$guidelines = array_merge($guidelines, $approvalTools);
		}
		$guidelines[] = '</BASE3-TOOL-GUIDELINES>';

		return trim(implode("\n\n", array_filter([
			$instructions,
			implode("\n", $guidelines)
		])));
	}

	private function requiresApproval(AgentCapability $capability): bool {
		$definition = $capability->getDefinition();
		$function = is_array($definition['function'] ?? null)
			? $definition['function']
			: [];
		$annotations = is_array($definition['annotations'] ?? null)
			? $definition['annotations']
			: [];
		if (is_array($function['annotations'] ?? null)) {
			$annotations = array_merge($annotations, $function['annotations']);
		}

		return (
			$annotations['requiresApproval']
			?? $definition['requiresApproval']
			?? $function['requiresApproval']
			?? false
		) === true;
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
