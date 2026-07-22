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

use AssistantFoundation\Api\IAgentRuntimeConfigFormService;
use AssistantFoundation\Api\IAiModelConfigurationProvider;
use Base3\Api\IRequest;
use JsonException;

final class NeuronAgentConfigFormService implements IAgentRuntimeConfigFormService {

	public function __construct(
		private readonly IRequest $request,
		private readonly IAiModelConfigurationProvider $modelConfigurationProvider
	) {}

	public static function getName(): string {
		return 'neuronagentconfigformservice';
	}

	public static function getRuntimeId(): string {
		return 'neuronai';
	}

	public function getDefaultSettings(): array {
		return [
			'llm' => '',
			'neuron_instructions' => '',
			'neuron_max_tool_runs' => 10,
			'neuron_mcp' => []
		];
	}

	public function normalizeSettings(array $settings): array {
		$defaults = $this->getDefaultSettings();

		return [
			'llm' => $this->normalizeTechnicalKey((string)($settings['llm'] ?? $defaults['llm'])),
			'neuron_instructions' => $this->normalizeTextBlock($this->readString($settings, 'neuron_instructions')),
			'neuron_max_tool_runs' => $this->normalizePositiveInt(
				$settings['neuron_max_tool_runs'] ?? $defaults['neuron_max_tool_runs'],
				(int)$defaults['neuron_max_tool_runs']
			),
			'neuron_mcp' => $this->removeSensitiveConfiguration(
				is_array($settings['neuron_mcp'] ?? null) ? $settings['neuron_mcp'] : []
			)
		];
	}

	public function getPostedSettings(array &$errors): array {
		$llm = $this->normalizeTechnicalKey((string)$this->request->request('llm', ''));
		$mcp = $this->decodeJsonObject(
			(string)$this->request->request('neuron_mcp', ''),
			'Neuron MCP configuration',
			$errors
		);

		if ($llm === '') {
			$errors[] = 'Please select a configured LLM for Neuron AI.';
		}
		elseif (!$this->modelConfigurationProvider->has($llm)) {
			$errors[] = 'Selected LLM is not available or cannot be resolved: ' . $llm;
		}
		if ($this->containsSensitiveConfiguration($mcp)) {
			$errors[] = 'Neuron MCP configuration must not contain credentials or secrets.';
		}

		return $this->normalizeSettings([
			'llm' => $llm,
			'neuron_instructions' => (string)$this->request->request('neuron_instructions', ''),
			'neuron_max_tool_runs' => $this->request->request('neuron_max_tool_runs', 10),
			'neuron_mcp' => $mcp
		]);
	}

	public function getPostedViewValues(): array {
		return $this->settingsToViewValues([
			'llm' => $this->request->request('llm', ''),
			'neuron_instructions' => $this->request->request('neuron_instructions', ''),
			'neuron_max_tool_runs' => $this->request->request('neuron_max_tool_runs', 10),
			'neuron_mcp' => $this->decodeJsonObjectSilently((string)$this->request->request('neuron_mcp', ''))
		]);
	}

	public function settingsToViewValues(array $settings): array {
		$settings = $this->normalizeSettings($settings);
		return array_merge($settings, [
			'neuron_mcp_json' => $this->formatJson($settings['neuron_mcp'], '{}')
		]);
	}

	public function getConfigurationSummary(array $settings): array {
		$settings = $this->normalizeSettings($settings);
		$llm = (string)$settings['llm'];
		foreach ($this->modelConfigurationProvider->getOptions() as $option) {
			if ((string)($option['id'] ?? '') !== $llm) {
				continue;
			}
			return [
				'provider' => (string)($option['driver'] ?? 'Neuron AI'),
				'model' => (string)($option['model'] ?? $llm)
			];
		}

		return [
			'provider' => 'Neuron AI',
			'model' => $llm
		];
	}

	public function getTemplate(): string {
		return DIR_PLUGIN . 'NeuronAi/tpl/Content/NeuronAgentConfigFormSection.php';
	}

	public function getTemplateData(array $values, array $options = []): array {
		$formId = trim((string)($options['form_id'] ?? 'base3_neuron_agent_config'));
		if ($formId === '') {
			$formId = 'base3_neuron_agent_config';
		}

		return [
			'form_id' => $formId,
			'values' => $values,
			'llm_options' => $this->modelConfigurationProvider->getOptions()
		];
	}

	/** @param array<string,mixed> $configuration */
	private function containsSensitiveConfiguration(array $configuration): bool {
		foreach ($configuration as $key => $value) {
			if ($this->isSensitiveKey((string)$key)) {
				return true;
			}
			if (is_array($value) && $this->containsSensitiveConfiguration($value)) {
				return true;
			}
		}

		return false;
	}

	/** @param array<string,mixed> $configuration @return array<string,mixed> */
	private function removeSensitiveConfiguration(array $configuration): array {
		$result = [];
		foreach ($configuration as $key => $value) {
			if ($this->isSensitiveKey((string)$key)) {
				continue;
			}
			$result[$key] = is_array($value)
				? $this->removeSensitiveConfiguration($value)
				: $value;
		}

		return $result;
	}

	private function isSensitiveKey(string $key): bool {
		$key = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $key) ?? $key), '_');
		if ($key === '') {
			return false;
		}

		if (in_array($key, [
			'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
			'authorization', 'credential', 'credentials', 'private_key',
			'client_secret', 'access_token', 'auth_token', 'bearer_token'
		], true)) {
			return true;
		}

		foreach (['_password', '_passwd', '_secret', '_api_key', '_private_key', '_access_token', '_auth_token', '_bearer_token'] as $suffix) {
			if (str_ends_with($key, $suffix)) {
				return true;
			}
		}

		return false;
	}

	/** @param array<string,mixed> $settings */
	private function readString(array $settings, string $key): string {
		$value = $settings[$key] ?? '';
		return is_scalar($value) || $value === null ? trim((string)$value) : '';
	}

	private function normalizePositiveInt(mixed $value, int $default): int {
		$value = is_numeric($value) ? (int)$value : $default;
		return $value > 0 ? $value : $default;
	}

	private function normalizeTextBlock(string $value): string {
		return str_replace(["\r\n", "\r"], "\n", $value);
	}

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	/** @param array<int,string> $errors @return array<string,mixed> */
	private function decodeJsonObject(string $raw, string $label, array &$errors): array {
		$raw = trim($raw);
		if ($raw === '') {
			return [];
		}
		try {
			$value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e) {
			$errors[] = $label . ' must be valid JSON: ' . $e->getMessage();
			return [];
		}
		if (!is_array($value)) {
			$errors[] = $label . ' must decode to a JSON object.';
			return [];
		}
		return $value;
	}

	/** @return array<string,mixed> */
	private function decodeJsonObjectSilently(string $raw): array {
		$raw = trim($raw);
		if ($raw === '') {
			return [];
		}
		$value = json_decode($raw, true);
		return is_array($value) ? $value : [];
	}

	/** @param array<string,mixed> $value */
	private function formatJson(array $value, string $empty): string {
		if ($value === []) {
			return $empty;
		}
		$json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return is_string($json) ? $json : $empty;
	}
}
