<?php declare(strict_types=1);

namespace NeuronAiTest\Service;

use NeuronAi\Service\NeuronExecutionEventMapper;
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

		$started = $mapper->map(new ToolCallChunk($tool));
		self::assertSame('tool.started', $started[0]->getName());
		self::assertSame('calculator', $started[0]->getPayload()['name']);

		$tool->setResult('10');
		$finished = $mapper->map(new ToolResultChunk($tool));
		self::assertSame('tool.finished', $finished[0]->getName());
		self::assertSame('10', $finished[0]->getPayload()['result']);
	}
}
