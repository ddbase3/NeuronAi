<?php declare(strict_types=1);

namespace NeuronAiTest\Dto;

use NeuronAi\Dto\NeuronAgentConfiguration;
use PHPUnit\Framework\TestCase;

final class NeuronAgentConfigurationTest extends TestCase {

	public function testUsesConfiguredLlmAndRequestInstructions(): void {
		$configuration = NeuronAgentConfiguration::fromArrays([
			'llm' => 'test-llm',
			'memory_profile' => 'neuronai-database',
			'context_profile' => 'ilias-default',
			'tool_profiles' => ['read-tools', 'reporting'],
			'neuron_max_tool_runs' => 4
		], [
			'system' => 'System instructions'
		]);

		self::assertSame('test-llm', $configuration->getLlmId());
		self::assertSame('System instructions', $configuration->getInstructions());
		self::assertSame('neuronai-database', $configuration->getMemoryProfile());
		self::assertSame('ilias-default', $configuration->getContextProfile());
		self::assertSame(['read-tools', 'reporting'], $configuration->getToolProfiles());
		self::assertSame(4, $configuration->getMaxToolRuns());
	}


	public function testPreservesNativeHistoryForExistingConfigurationWithoutMemoryProfile(): void {
		$configuration = NeuronAgentConfiguration::fromArrays([
			'llm' => 'test-llm'
		], []);

		self::assertSame(NeuronAgentConfiguration::DEFAULT_MEMORY_PROFILE, $configuration->getMemoryProfile());
	}

	public function testAllowsExplicitlyDisabledConversationMemory(): void {
		$configuration = NeuronAgentConfiguration::fromArrays([
			'llm' => 'test-llm',
			'memory_profile' => ''
		], []);

		self::assertSame('', $configuration->getMemoryProfile());
	}

	public function testDisablesMcpForSuggestionExecutions(): void {
		$configuration = NeuronAgentConfiguration::fromArrays([
			'llm' => 'test-llm',
			'neuron_mcp' => ['url' => 'https://example.invalid/mcp']
		], [
			'mode' => 'suggestions'
		]);

		self::assertNull($configuration->getMcp());
	}

	public function testRequiresConfiguredLlm(): void {
		$this->expectException(\InvalidArgumentException::class);
		NeuronAgentConfiguration::fromArrays([], []);
	}
}
