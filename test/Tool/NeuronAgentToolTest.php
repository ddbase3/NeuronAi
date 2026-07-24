<?php declare(strict_types=1);

namespace NeuronAiTest\Tool;

use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentToolResult;
use NeuronAi\Tool\NeuronAgentTool;
use PHPUnit\Framework\TestCase;

final class NeuronAgentToolTest extends TestCase {

	public function testAdaptsSchemaAndExecutesToolSet(): void {
		$capability = $this->createCapability();
		$set = new class($capability) implements IAgentToolSet {
			private AgentCapabilityCatalog $catalog;
			public array $metadata = [];
			public function __construct(AgentCapability $capability) {
				$this->catalog = new AgentCapabilityCatalog([$capability]);
			}
			public function getCatalog(): AgentCapabilityCatalog { return $this->catalog; }
			public function getWarnings(): array { return []; }
			public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): AgentToolResult {
				$this->metadata = $metadata;
				return AgentToolResult::success($callId, $toolName, $arguments, ['echo' => $arguments['value']]);
			}
		};
		$tool = new NeuronAgentTool($capability, $set);
		$tool->prepareExecution(2, 3);

		$tool->setCallId('call-1')->setInputs(['value' => 'Atlas'])->execute();

		self::assertSame('echo_value', $tool->getName());
		self::assertSame(['value'], $tool->getRequiredProperties());
		self::assertSame(['echo' => 'Atlas'], json_decode($tool->getResult(), true));
		self::assertTrue($tool->getExecutionResult()?->isSuccess());
		self::assertSame('Echo value', $set->metadata['label'] ?? null);
		self::assertSame(2, $set->metadata['iteration'] ?? null);
		self::assertSame(3, $set->metadata['call_index'] ?? null);
	}

	public function testFailureBecomesToolResultInsteadOfAbortingAgent(): void {
		$capability = $this->createCapability();
		$set = new class($capability) implements IAgentToolSet {
			private AgentCapabilityCatalog $catalog;
			public function __construct(AgentCapability $capability) {
				$this->catalog = new AgentCapabilityCatalog([$capability]);
			}
			public function getCatalog(): AgentCapabilityCatalog { return $this->catalog; }
			public function getWarnings(): array { return []; }
			public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): AgentToolResult {
				return AgentToolResult::failure($callId, $toolName, $arguments, 'denied', 'Denied');
			}
		};
		$tool = new NeuronAgentTool($capability, $set);

		$tool->setCallId('call-2')->setInputs(['value' => 'Atlas'])->execute();
		$result = json_decode($tool->getResult(), true);

		self::assertSame('failure', $result['status'] ?? null);
		self::assertSame('denied', $result['error_code'] ?? null);
		self::assertFalse($tool->getExecutionResult()?->isSuccess());
	}

	private function createCapability(): AgentCapability {
		return new AgentCapability(
			'echo_value',
			'Echo value',
			'Echoes a value.',
			'test',
			[],
			0,
			[
				'type' => 'function',
				'readOnlyHint' => true,
				'function' => [
					'name' => 'echo_value',
					'description' => 'Echoes a value.',
					'parameters' => [
						'type' => 'object',
						'properties' => ['value' => ['type' => 'string']],
						'required' => ['value']
					]
				]
			]
		);
	}
}
