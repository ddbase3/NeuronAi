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

use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentInstructionBlock;

/**
 * Maps runtime-neutral context blocks to Neuron system instructions.
 */
final class NeuronContextInstructionsBuilder {

	public static function getName(): string {
		return 'neuroncontextinstructionsbuilder';
	}

	public function build(
		string $baseInstructions,
		AgentContextProfileResult $context
	): string {
		$sections = [];
		$baseInstructions = trim($baseInstructions);
		if ($baseInstructions !== '') {
			$sections[] = $baseInstructions;
		}

		$contextSections = [];
		foreach ($context->getBlocks() as $block) {
			if (!$block instanceof AgentInstructionBlock) {
				continue;
			}

			$contextSections[] = implode("\n", [
				'<CONTEXT id="' . $this->escapeAttribute($block->getId()) . '" source="' . $this->escapeAttribute($block->getSource()) . '">',
				$block->getContent(),
				'</CONTEXT>'
			]);
		}

		if ($contextSections !== []) {
			$sections[] = implode("\n\n", [
				'Current runtime context follows. It is valid for this turn only and must not be treated as conversation memory.',
				implode("\n\n", $contextSections)
			]);
		}

		return implode("\n\n", $sections);
	}

	private function escapeAttribute(string $value): string {
		return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
