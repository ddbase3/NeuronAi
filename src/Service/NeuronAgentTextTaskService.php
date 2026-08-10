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

use AssistantFoundation\Api\IAgentContextProfileService;
use AssistantFoundation\Api\IAgentTextTaskRuntimeService;
use AssistantFoundation\Api\IAgentToolProfileService;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentTextTaskRequest;
use AssistantFoundation\Dto\AgentTextTaskResult;
use NeuronAi\Api\INeuronAgentFactory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;

/**
 * Executes isolated Neuron model calls without persistent chat history or tools.
 */
final class NeuronAgentTextTaskService implements IAgentTextTaskRuntimeService {

	public function __construct(
		private readonly INeuronAgentFactory $agentFactory,
		private readonly IAgentContextProfileService $contextProfileService,
		private readonly IAgentToolProfileService $toolProfileService,
		private readonly NeuronContextInstructionsBuilder $contextInstructionsBuilder
	) {}

	public static function getName(): string {
		return 'neuronagenttexttaskservice';
	}

	public static function getRuntimeId(): string {
		return 'neuronai';
	}

	public function executeTextTask(AgentTextTaskRequest $request): AgentTextTaskResult {
		$baseConfiguration = NeuronAgentConfiguration::fromArrays(
			$request->getAgentConfiguration(),
			[
				'system' => $request->getSystemPrompt(),
				'prompt' => $request->getPrompt(),
				'mode' => 'text-task'
			]
		);
		$executionRequest = $this->createExecutionRequest($request);
		$instructions = $request->getSystemPrompt();
		$warnings = [];

		if ($request->shouldIncludeContextProfile()) {
			$contextResult = $this->contextProfileService->build(
				$baseConfiguration->getContextProfile(),
				$executionRequest
			);
			$instructions = $this->contextInstructionsBuilder->build($instructions, $contextResult);
			$warnings = array_merge($warnings, $contextResult->getWarnings());
		}

		if ($request->shouldIncludeToolProfile()) {
			$toolSet = $this->toolProfileService->resolve(
				$baseConfiguration->getToolProfiles(),
				$executionRequest
			);
			$instructions = $this->appendCapabilityCatalog($instructions, $toolSet->getCatalog()->all());
			$warnings = array_merge($warnings, $toolSet->getWarnings());
		}

		$taskConfiguration = new NeuronAgentConfiguration(
			$baseConfiguration->getLlmId(),
			$instructions,
			'',
			[],
			1,
			null,
			''
		);
		$message = $this->agentFactory
			->create($taskConfiguration, $executionRequest)
			->chat(new UserMessage($request->getPrompt()))
			->getMessage();
		$content = trim((string)$message->getContent());
		if ($content === '') {
			throw new \RuntimeException('Neuron AI text task returned an empty response.');
		}

		$usage = $message->getUsage();
		$metadata = [
			'task' => $request->getTaskName(),
			'runtime' => self::getRuntimeId(),
			'context_profile_included' => $request->shouldIncludeContextProfile(),
			'tool_profile_included' => $request->shouldIncludeToolProfile()
		];
		if ($usage !== null) {
			$metadata['usage'] = $usage->jsonSerialize();
		}

		return new AgentTextTaskResult(
			$content,
			array_values(array_unique($warnings)),
			$metadata
		);
	}

	private function createExecutionRequest(AgentTextTaskRequest $request): AgentExecutionRequest {
		$context = $request->getContext();
		unset($context['conversation_id'], $context['conversation_owner_key']);
		$context['source'] = 'agent-text-task';
		$context['agent_text_task'] = $request->getTaskName();

		return new AgentExecutionRequest(
			$request->getAgentConfiguration(),
			[
				'system' => $request->getSystemPrompt(),
				'prompt' => $request->getPrompt(),
				'mode' => 'text-task'
			],
			$context
		);
	}

	/** @param array<int,AgentCapability> $capabilities */
	private function appendCapabilityCatalog(string $instructions, array $capabilities): string {
		$catalog = [];
		foreach ($capabilities as $capability) {
			if (!$capability instanceof AgentCapability) {
				continue;
			}

			$catalog[] = [
				'name' => $capability->getName(),
				'title' => $capability->getTitle(),
				'description' => $capability->getDescription()
			];
		}
		if ($catalog === []) {
			return trim($instructions);
		}

		$json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			throw new \RuntimeException('Neuron AI text task could not encode the tool capability catalog.');
		}

		return trim(implode("\n\n", array_filter([
			trim($instructions),
			'Available configured capabilities are listed below. Describe only capabilities present in this catalog. Do not call tools.'
				. "\n" . $json
		])));
	}
}
