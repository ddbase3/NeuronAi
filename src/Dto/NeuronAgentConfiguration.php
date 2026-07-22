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

namespace NeuronAi\Dto;

/**
 * Immutable normalized settings for one Neuron AI execution.
 */
final class NeuronAgentConfiguration {

	private const DEFAULT_MAX_TOOL_RUNS = 10;

	/** @param array<string,mixed>|null $mcp */
	public function __construct(
		private readonly string $llmId,
		private readonly string $instructions,
		private readonly int $maxToolRuns = self::DEFAULT_MAX_TOOL_RUNS,
		private readonly ?array $mcp = null
	) {}

	/**
	 * @param array<string,mixed> $agentConfiguration
	 * @param array<string,mixed> $inputs
	 */
	public static function fromArrays(array $agentConfiguration, array $inputs): self {
		$llmId = self::normalizeKey(self::readString($agentConfiguration, 'llm'));
		$instructions = self::readString(
			$agentConfiguration,
			'neuron_instructions',
			self::readString($inputs, 'system')
		);
		$maxToolRuns = self::readPositiveInt(
			$agentConfiguration,
			'neuron_max_tool_runs',
			self::DEFAULT_MAX_TOOL_RUNS
		);
		$mcp = self::readString($inputs, 'mode') === 'suggestions'
			? null
			: self::normalizeMcp(self::readArray($agentConfiguration, 'neuron_mcp'));

		if ($llmId === '') {
			throw new \InvalidArgumentException('Neuron AI requires a configured LLM.');
		}

		return new self($llmId, $instructions, $maxToolRuns, $mcp);
	}

	public function getLlmId(): string { return $this->llmId; }
	public function getInstructions(): string { return $this->instructions; }
	public function getMaxToolRuns(): int { return $this->maxToolRuns; }
	/** @return array<string,mixed>|null */ public function getMcp(): ?array { return $this->mcp; }

	/** @param array<string,mixed> $values */
	private static function readString(array $values, string $key, string $default = ''): string {
		$value = $values[$key] ?? $default;
		return is_scalar($value) || $value === null ? trim((string)$value) : $default;
	}

	/** @param array<string,mixed> $values @return array<string,mixed> */
	private static function readArray(array $values, string $key): array {
		$value = $values[$key] ?? [];
		return is_array($value) ? $value : [];
	}

	/** @param array<string,mixed> $values */
	private static function readPositiveInt(array $values, string $key, int $default): int {
		$value = $values[$key] ?? $default;
		$value = is_numeric($value) ? (int)$value : $default;
		return $value > 0 ? $value : $default;
	}

	/** @param array<string,mixed> $mcp @return array<string,mixed>|null */
	private static function normalizeMcp(array $mcp): ?array {
		if ($mcp === []) {
			return null;
		}

		$url = self::readString($mcp, 'url');
		$command = self::readString($mcp, 'command');
		if ($url === '' && $command === '') {
			throw new \InvalidArgumentException('Neuron MCP configuration requires url or command.');
		}

		foreach (['only', 'exclude'] as $key) {
			if (isset($mcp[$key]) && !is_array($mcp[$key])) {
				throw new \InvalidArgumentException('Neuron MCP ' . $key . ' must be an array.');
			}
		}

		unset($mcp['token'], $mcp['authorization'], $mcp['api_key']);
		return $mcp;
	}

	private static function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
