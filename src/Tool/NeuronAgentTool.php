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

namespace NeuronAi\Tool;

use AssistantFoundation\Api\IAgentConfirmableToolSet;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentToolResult;
use NeuronAi\Exception\NeuronToolSuspensionException;
use NeuronAi\Vendor\NeuronAI\Tools\ArrayProperty;
use NeuronAi\Vendor\NeuronAI\Tools\ObjectProperty;
use NeuronAi\Vendor\NeuronAI\Tools\PropertyType;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use NeuronAi\Vendor\NeuronAI\Tools\ToolProperty;
use NeuronAi\Vendor\NeuronAI\Tools\ToolPropertyInterface;

/**
 * Neuron-native adapter for one runtime-neutral BASE3 tool capability.
 */
final class NeuronAgentTool extends Tool {

	private ?AgentToolResult $executionResult = null;

	/** @var array<string,mixed> */
	private array $executionMetadata = [];

	private readonly string $displayLabel;

	private readonly bool $requiresApproval;

	public function __construct(
		AgentCapability $capability,
		private readonly IAgentToolSet $toolSet
	) {
		$definition = $capability->getDefinition();
		$function = is_array($definition['function'] ?? null)
			? $definition['function']
			: $definition;
		$annotations = is_array($definition['annotations'] ?? null)
			? $definition['annotations']
			: [];
		if (is_array($function['annotations'] ?? null)) {
			$annotations = array_merge($annotations, $function['annotations']);
		}

		$this->displayLabel = trim($capability->getTitle()) !== ''
			? trim($capability->getTitle())
			: $capability->getName();
		$this->requiresApproval = (
			$annotations['requiresApproval']
			?? $definition['requiresApproval']
			?? $function['requiresApproval']
			?? false
		) === true;

		parent::__construct(
			$capability->getName(),
			$capability->getDescription(),
			[],
			[],
			$annotations
		);

		$parameters = is_array($function['parameters'] ?? null)
			? $function['parameters']
			: [];
		$required = $this->normalizeStringList($parameters['required'] ?? []);
		$properties = is_array($parameters['properties'] ?? null)
			? $parameters['properties']
			: [];

		foreach ($properties as $name => $schema) {
			if (!is_string($name) || $name === '' || !is_array($schema)) {
				continue;
			}
			$this->addProperty($this->createProperty(
				$name,
				$schema,
				in_array($name, $required, true)
			));
		}
	}

	public function prepareExecution(int $iteration, int $callIndex): void {
		$this->executionMetadata = [
			'source' => 'direct',
			'label' => $this->displayLabel,
			'iteration' => max(1, $iteration),
			'call_index' => max(1, $callIndex)
		];
	}

	public function execute(): void {
		$callId = trim((string)($this->getCallId() ?? ''));
		if ($callId === '') {
			$callId = uniqid('tool_', true);
			$this->setCallId($callId);
		}

		if ($this->requiresApproval && !$this->toolSet instanceof IAgentConfirmableToolSet) {
			throw new \RuntimeException('Tool requires approval but the resolved tool set cannot suspend execution: ' . $this->getName());
		}

		if ($this->toolSet instanceof IAgentConfirmableToolSet) {
			$suspension = $this->toolSet->prepareSuspension(
				$callId,
				$this->getName(),
				$this->getInputs(),
				$this->executionMetadata
			);
			if ($suspension !== null) {
				throw new NeuronToolSuspensionException($suspension);
			}
			if ($this->requiresApproval) {
				throw new \RuntimeException('Tool requires approval but no suspension was created: ' . $this->getName());
			}
		}

		$this->applyExecutionResult($this->toolSet->execute(
			$callId,
			$this->getName(),
			$this->getInputs(),
			$this->executionMetadata
		));
	}

	public function applyExecutionResult(AgentToolResult $result): void {
		$this->executionResult = $result;
		$this->setResult($result->isSuccess() ? $result->getOutput() : $result->toArray());
	}

	public function getExecutionResult(): ?AgentToolResult {
		return $this->executionResult;
	}

	public function getDisplayLabel(): string {
		return $this->displayLabel;
	}

	/** @return array<string,mixed> */
	public function getExecutionMetadata(): array {
		return $this->executionMetadata;
	}

	/** @param array<string,mixed> $schema */
	private function createProperty(string $name, array $schema, bool $required): ToolPropertyInterface {
		$type = $this->resolveType($schema['type'] ?? 'string');
		$description = isset($schema['description']) && is_scalar($schema['description'])
			? trim((string)$schema['description'])
			: null;

		if ($type === PropertyType::ARRAY) {
			$items = is_array($schema['items'] ?? null)
				? $this->createProperty($name . '_item', $schema['items'], false)
				: new ToolProperty($name . '_item', PropertyType::STRING);

			return new ArrayProperty(
				$name,
				$description,
				$required,
				$items,
				isset($schema['minItems']) && is_numeric($schema['minItems']) ? (int)$schema['minItems'] : null,
				isset($schema['maxItems']) && is_numeric($schema['maxItems']) ? (int)$schema['maxItems'] : null
			);
		}

		if ($type === PropertyType::OBJECT) {
			$nestedRequired = $this->normalizeStringList($schema['required'] ?? []);
			$nested = [];
			foreach (is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $nestedName => $nestedSchema) {
				if (!is_string($nestedName) || $nestedName === '' || !is_array($nestedSchema)) {
					continue;
				}
				$nested[] = $this->createProperty(
					$nestedName,
					$nestedSchema,
					in_array($nestedName, $nestedRequired, true)
				);
			}
			return new ObjectProperty($name, $description, $required, null, $nested);
		}

		$enum = is_array($schema['enum'] ?? null)
			? array_values(array_filter($schema['enum'], static fn(mixed $value): bool => is_scalar($value)))
			: [];

		return new ToolProperty($name, $type, $description, $required, $enum);
	}

	private function resolveType(mixed $value): PropertyType {
		try {
			if (is_array($value)) {
				$value = array_values(array_filter(
					$value,
					static fn(mixed $item): bool => is_string($item) && $item !== 'null'
				));
			}
			return PropertyType::fromSchema(is_array($value) || is_string($value) ? $value : 'string');
		}
		catch (\Throwable) {
			return PropertyType::STRING;
		}
	}

	/** @return array<int,string> */
	private function normalizeStringList(mixed $values): array {
		if (!is_array($values)) {
			return [];
		}
		$result = [];
		foreach ($values as $value) {
			if (!is_scalar($value)) {
				continue;
			}
			$value = trim((string)$value);
			if ($value !== '') {
				$result[$value] = $value;
			}
		}
		return array_values($result);
	}
}
