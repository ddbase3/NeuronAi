<?php declare(strict_types=1);

namespace NeuronAiTest\Service;

use AssistantFoundation\Dto\AgentContextProfileResult;
use AssistantFoundation\Dto\AgentInstructionBlock;
use NeuronAi\Service\NeuronContextInstructionsBuilder;
use PHPUnit\Framework\TestCase;

final class NeuronContextInstructionsBuilderTest extends TestCase {

	public function testKeepsBaseInstructionsAndAddsTurnLocalContext(): void {
		$result = new AgentContextProfileResult('ilias-default', [
			new AgentInstructionBlock(
				'current-time',
				'Current time is 2026-07-22T20:00:00+02:00.',
				10,
				'time'
			),
			new AgentInstructionBlock(
				'ilias-page',
				'Current ILIAS page is Course Atlas.',
				20,
				'ilias'
			)
		]);

		$instructions = (new NeuronContextInstructionsBuilder())->build(
			'You are a helpful assistant.',
			$result
		);

		self::assertStringStartsWith('You are a helpful assistant.', $instructions);
		self::assertStringContainsString('valid for this turn only', $instructions);
		self::assertStringContainsString('<CONTEXT id="current-time" source="time">', $instructions);
		self::assertStringContainsString('Current ILIAS page is Course Atlas.', $instructions);
	}

	public function testReturnsBaseInstructionsWhenNoContextExists(): void {
		$instructions = (new NeuronContextInstructionsBuilder())->build(
			'Base instructions',
			AgentContextProfileResult::empty()
		);

		self::assertSame('Base instructions', $instructions);
	}
}
