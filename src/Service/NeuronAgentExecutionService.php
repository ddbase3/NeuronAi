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

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentRuntimeService;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentResult;
use AssistantFoundation\Dto\AgentState;
use NeuronAi\Api\INeuronAgentFactory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;

/**
 * Neuron AI implementation of the transport-neutral agent execution service.
 */
final class NeuronAgentExecutionService implements IAgentRuntimeService {

	private const ASSISTANT_OUTPUT_ID = 'assistant';

	public function __construct(
		private readonly INeuronAgentFactory $agentFactory,
		private readonly NeuronExecutionEventMapper $eventMapper
	) {}

	public static function getName(): string {
		return 'neuronagentexecutionservice';
	}

	public static function getRuntimeId(): string {
		return 'neuronai';
	}

	public static function getRuntimeLabel(): string {
		return 'Neuron AI';
	}

	public static function getRuntimeDescription(): string {
		return 'Executes Neuron AI agents with provider streaming and optional MCP tools.';
	}

	public static function getDefaultPriority(): int {
		return 0;
	}

	public function execute(
		AgentExecutionRequest $request,
		?IAgentEventSink $eventSink = null
	): AgentExecutionResult {
		$inputs = $request->getInputs();
		$prompt = $this->readString($inputs, 'prompt');
		if ($prompt === '') {
			throw new \InvalidArgumentException('Neuron AI prompt is required.');
		}

		$configuration = NeuronAgentConfiguration::fromArrays(
			$request->getAgentConfiguration(),
			$inputs
		);
		$messageId = uniqid('msg_', true);
		$this->emit($eventSink, 'msgid', ['id' => $messageId]);

		try {
			$handler = $this->agentFactory
				->create($configuration, $request)
				->stream(new UserMessage($prompt));
			$toolCalls = [];
			$cancelled = false;

			foreach ($handler->events() as $event) {
				if ($eventSink?->isCancelled() === true) {
					$cancelled = true;
					break;
				}
				foreach ($this->eventMapper->map($event) as $mappedEvent) {
					if ($mappedEvent->getName() === 'tool.finished') {
						$toolCalls[] = $mappedEvent->getPayload();
					}
					$eventSink?->emit($mappedEvent);
				}
			}

			if ($cancelled) {
				return $this->createResult(
					$messageId,
					'',
					AgentExecutionStatus::PARTIAL,
					$toolCalls,
					['Execution was cancelled by the event sink.']
				);
			}

			$message = $handler->getMessage();
			$content = trim((string)($message->getContent() ?? ''));
			if ($content === '') {
				throw new \RuntimeException('Neuron AI completed without assistant content.');
			}

			$this->emit($eventSink, 'done', ['status' => AgentExecutionStatus::COMPLETED]);
			return $this->createResult(
				$messageId,
				$content,
				AgentExecutionStatus::COMPLETED,
				$toolCalls
			);
		}
		catch (\Throwable $e) {
			$this->emit($eventSink, 'error', [
				'message' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			]);
			$this->emit($eventSink, 'done', ['status' => AgentExecutionStatus::FAILED]);
			return $this->createFailureResult($messageId, $e);
		}
	}

	/** @param array<string,mixed> $payload */
	private function emit(?IAgentEventSink $eventSink, string $name, array $payload = []): void {
		if ($eventSink === null || $eventSink->isCancelled()) {
			return;
		}
		$eventSink->emit(new AgentExecutionEvent($name, $payload));
	}

	/**
	 * @param array<int,array<string,mixed>> $toolCalls
	 * @param array<int,string> $warnings
	 */
	private function createResult(
		string $messageId,
		string $content,
		string $status,
		array $toolCalls = [],
		array $warnings = []
	): AgentExecutionResult {
		$message = [
			'id' => $messageId,
			'role' => 'assistant',
			'content' => $content
		];
		$output = [
			self::ASSISTANT_OUTPUT_ID => [
				'message' => $message,
				'tool_calls' => $toolCalls,
				'status' => $status
			]
		];
		$agentResult = new AgentResult(
			$status,
			AgentState::empty(),
			$output,
			['runtime' => 'neuronai']
		);
		return new AgentExecutionResult($output, $warnings, $agentResult);
	}

	private function createFailureResult(string $messageId, \Throwable $error): AgentExecutionResult {
		$output = [
			self::ASSISTANT_OUTPUT_ID => [
				'error' => $error->getMessage(),
				'tool_calls' => [],
				'status' => AgentExecutionStatus::FAILED
			]
		];
		$agentResult = new AgentResult(
			AgentExecutionStatus::FAILED,
			AgentState::empty(),
			$output,
			[
				'runtime' => 'neuronai',
				'error_type' => get_class($error)
			]
		);
		return new AgentExecutionResult($output, [], $agentResult);
	}

	/** @param array<string,mixed> $values */
	private function readString(array $values, string $key): string {
		$value = $values[$key] ?? '';
		return is_scalar($value) || $value === null ? trim((string)$value) : '';
	}
}
