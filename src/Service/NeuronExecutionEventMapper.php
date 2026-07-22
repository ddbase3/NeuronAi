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
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\AudioChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ImageChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ReasoningChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;

final class NeuronExecutionEventMapper {

	/** @return array<int,AgentExecutionEvent> */
	public function map(mixed $event): array {
		if ($event instanceof TextChunk) {
			return [new AgentExecutionEvent('token', ['text' => $event->content])];
		}
		if ($event instanceof ReasoningChunk) {
			return [new AgentExecutionEvent('reasoning.delta', ['text' => $event->content])];
		}
		if ($event instanceof ToolCallChunk) {
			return [new AgentExecutionEvent('tool.started', $this->toolPayload($event->tool, false))];
		}
		if ($event instanceof ToolResultChunk) {
			return [new AgentExecutionEvent('tool.finished', $this->toolPayload($event->tool, true))];
		}
		if ($event instanceof AudioChunk) {
			return [new AgentExecutionEvent('response.audio', ['content' => $event->content])];
		}
		if ($event instanceof ImageChunk) {
			return [new AgentExecutionEvent('response.image', ['content' => $event->content])];
		}
		return [];
	}

	/** @return array<string,mixed> */
	private function toolPayload(ToolInterface $tool, bool $includeResult): array {
		$payload = [
			'id' => $tool->getCallId(),
			'name' => $tool->getName(),
			'arguments' => $tool->getInputs()
		];
		if ($includeResult) {
			$serialized = $tool->jsonSerialize();
			$payload['result'] = $serialized['result'] ?? null;
		}
		return $payload;
	}
}
