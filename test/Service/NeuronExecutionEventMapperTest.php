<?php declare(strict_types=1);

namespace NeuronAiTest\Service;

use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapability;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentToolResult;
use NeuronAi\Service\NeuronExecutionEventMapper;
use NeuronAi\Tool\NeuronAgentTool;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use PHPUnit\Framework\TestCase;

final class NeuronExecutionEventMapperTest extends TestCase {

	public function testMapsTextChunkToChatbotTokenEvent(): void {
		$events = (new NeuronExecutionEventMapper())->map(new TextChunk('message-1', 'hello'));

		self::assertCount(1, $events);
		self::assertSame('token', $events[0]->getName());
		self::assertSame(['text' => 'hello'], $events[0]->getPayload());
	}

	public function testMapsToolLifecycleEvents(): void {
		$tool = Tool::make('calculator')
			->setCallId('call-1')
			->setInputs(['value' => 5]);
		$mapper = new NeuronExecutionEventMapper();
		$mapper->beginExecution();

		$started = $mapper->map(new ToolCallChunk($tool));
		self::assertSame('tool.started', $started[0]->getName());
		self::assertSame('calculator', $started[0]->getPayload()['name']);
		self::assertSame('call-1', $started[0]->getPayload()['call_id']);
		self::assertSame(['value' => 5], $started[0]->getPayload()['args']);

		$tool->setResult('10');
		$finished = $mapper->map(new ToolResultChunk($tool));
		self::assertSame('tool.finished', $finished[0]->getName());
		self::assertSame('10', $finished[0]->getPayload()['result']);
	}

	public function testPreparesNeuronToolAuditMetadataAndDisplayPayload(): void {
		$capability = new AgentCapability(
			'system_status',
			'System Status',
			'Reads the system status.',
			'system',
			[],
			0,
			[
				'type' => 'function',
				'function' => [
					'name' => 'system_status',
					'description' => 'Reads the system status.',
					'parameters' => ['type' => 'object', 'properties' => [], 'required' => []]
				]
			]
		);
		$toolSet = new class($capability) implements IAgentToolSet {
			private AgentCapabilityCatalog $catalog;
			public function __construct(AgentCapability $capability) {
				$this->catalog = new AgentCapabilityCatalog([$capability]);
			}
			public function getCatalog(): AgentCapabilityCatalog { return $this->catalog; }
			public function getWarnings(): array { return []; }
			public function execute(string $callId, string $toolName, array $arguments, array $metadata = []): AgentToolResult {
				return AgentToolResult::success($callId, $toolName, $arguments, ['ok' => true], $metadata);
			}
		};
		$tool = new NeuronAgentTool($capability, $toolSet);
		$tool->setCallId('call-2')->setInputs([]);
		$mapper = new NeuronExecutionEventMapper();
		$mapper->beginExecution();

		$started = $mapper->map(new ToolCallChunk($tool))[0]->getPayload();
		self::assertSame('System Status', $started['label']);
		self::assertSame('system_status', $started['tool']);
		self::assertSame(1, $started['iteration']);
		self::assertSame(1, $started['call_index']);

		$tool->execute();
		$finished = $mapper->map(new ToolResultChunk($tool))[0]->getPayload();
		self::assertSame('System Status', $finished['label']);
		self::assertSame(1, $finished['iteration']);
		self::assertSame(1, $finished['call_index']);
	}
}
