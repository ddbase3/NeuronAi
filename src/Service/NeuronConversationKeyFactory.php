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
use NeuronAi\Dto\NeuronConversationScope;

/**
 * Builds a persistent conversation scope from server-owned request context.
 */
final class NeuronConversationKeyFactory {

	public static function getName(): string {
		return 'neuronconversationkeyfactory';
	}

	public function create(AgentExecutionRequest $request): ?NeuronConversationScope {
		$context = $request->getContext();
		$conversationId = $this->normalizeConversationId($this->readString($context, 'conversation_id'));
		$ownerKey = $this->normalizeOwnerKey($this->readString($context, 'conversation_owner_key'));
		$configGroup = $this->normalizeConfigKey($this->readString($context, 'chatbot_config_group'));
		$configName = $this->normalizeConfigKey($this->readString($context, 'chatbot_config_name'));

		if ($conversationId === '' || $ownerKey === '' || $configGroup === '' || $configName === '') {
			return null;
		}

		$conversationKey = hash('sha256', implode("\0", [
			'neuronai',
			$ownerKey,
			$configGroup,
			$configName,
			$conversationId
		]));

		return new NeuronConversationScope(
			$conversationKey,
			$conversationId,
			$ownerKey,
			$configGroup,
			$configName
		);
	}

	/** @param array<string,mixed> $values */
	private function readString(array $values, string $key): string {
		$value = $values[$key] ?? '';

		return is_scalar($value) || $value === null ? trim((string)$value) : '';
	}

	private function normalizeOwnerKey(string $value): string {
		$value = strtolower(trim($value));

		return preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : '';
	}

	private function normalizeConversationId(string $value): string {
		$value = substr(trim($value), 0, 100);

		return preg_replace('/[^A-Za-z0-9._:-]+/', '', $value) ?? '';
	}

	private function normalizeConfigKey(string $value): string {
		return substr(trim($value), 0, 191);
	}
}
