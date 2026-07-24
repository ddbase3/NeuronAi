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

use AssistantFoundation\Dto\AgentExecutionEvent;
use NeuronAi\Tool\NeuronAgentTool;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\AudioChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ImageChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ReasoningChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;

final class NeuronExecutionEventMapper {

	private int $callIndex = 0;

	/** @var array<string,int> */
	private array $toolRuns = [];

	public function beginExecution(): void {
		$this->callIndex = 0;
		$this->toolRuns = [];
	}

	/** @return array<int,AgentExecutionEvent> */
	public function map(mixed $event): array {
		if ($event instanceof TextChunk) {
			return [new AgentExecutionEvent('token', ['text' => $event->content])];
		}
		if ($event instanceof ReasoningChunk) {
			return [new AgentExecutionEvent('reasoning.delta', ['text' => $event->content])];
		}
		if ($event instanceof ToolCallChunk) {
			$this->prepareToolExecution($event->tool);
			return [new AgentExecutionEvent('tool.started', $this->toolPayload($event->tool, false))];
		}
		if ($event instanceof ToolResultChunk) {
			$result = $event->tool instanceof NeuronAgentTool
				? $event->tool->getExecutionResult()
				: null;
			$name = $result !== null && !$result->isSuccess()
				? 'tool.failed'
				: 'tool.finished';
			return [new AgentExecutionEvent($name, $this->toolPayload($event->tool, true))];
		}
		if ($event instanceof AudioChunk) {
			return [new AgentExecutionEvent('response.audio', ['content' => $event->content])];
		}
		if ($event instanceof ImageChunk) {
			return [new AgentExecutionEvent('response.image', ['content' => $event->content])];
		}
		return [];
	}

	private function prepareToolExecution(ToolInterface $tool): void {
		if (!$tool instanceof NeuronAgentTool) {
			return;
		}

		$callId = trim((string)($tool->getCallId() ?? ''));
		if ($callId === '') {
			$tool->setCallId(uniqid('toolcall-', true));
		}

		$name = $tool->getName();
		$this->callIndex++;
		$this->toolRuns[$name] = ($this->toolRuns[$name] ?? 0) + 1;
		$tool->prepareExecution($this->toolRuns[$name], $this->callIndex);
	}

	/** @return array<string,mixed> */
	private function toolPayload(ToolInterface $tool, bool $includeResult): array {
		$metadata = $tool instanceof NeuronAgentTool
			? $tool->getExecutionMetadata()
			: [];
		$callId = trim((string)($tool->getCallId() ?? ''));
		$label = $tool instanceof NeuronAgentTool
			? $tool->getDisplayLabel()
			: $tool->getName();
		$arguments = $tool->getInputs();
		$payload = [
			'id' => $callId,
			'call_id' => $callId,
			'name' => $tool->getName(),
			'tool' => $tool->getName(),
			'label' => $label,
			'arguments' => $arguments,
			'args' => $arguments,
			'iteration' => max(0, (int)($metadata['iteration'] ?? 0)),
			'call_index' => max(0, (int)($metadata['call_index'] ?? 0))
		];
		if ($includeResult) {
			$serialized = $tool->jsonSerialize();
			$payload['result'] = $serialized['result'] ?? null;
			if ($tool instanceof NeuronAgentTool && $tool->getExecutionResult() !== null) {
				$result = $tool->getExecutionResult();
				$payload['execution'] = $result->toArray();
				if (!$result->isSuccess()) {
					$payload['error'] = $result->getErrorMessage();
					$payload['error_code'] = $result->getErrorCode();
				}
			}
		}
		return $payload;
	}
}
