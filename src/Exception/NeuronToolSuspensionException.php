<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 **********************************************************************/

namespace NeuronAi\Exception;

use AssistantFoundation\Dto\AgentSuspension;

/** Internal control-flow exception used to pause one Neuron tool call. */
final class NeuronToolSuspensionException extends \RuntimeException {

	public function __construct(private readonly AgentSuspension $suspension) {
		parent::__construct('Neuron tool execution requires explicit user approval.');
	}

	public function getSuspension(): AgentSuspension {
		return $this->suspension;
	}
}
