<?php declare(strict_types=1);

namespace NeuronAiTest\Service;

use AssistantFoundation\Dto\AgentExecutionRequest;
use NeuronAi\Service\NeuronConversationKeyFactory;
use PHPUnit\Framework\TestCase;

final class NeuronConversationKeyFactoryTest extends TestCase {

	public function testBuildsStableScopeFromServerOwnedContext(): void {
		$ownerKey = str_repeat('a', 64);
		$request = new AgentExecutionRequest([], [], [
			'conversation_id' => 'conversation-1',
			'conversation_owner_key' => $ownerKey,
			'chatbot_config_group' => 'chatbot',
			'chatbot_config_name' => 'example'
		]);

		$scope = (new NeuronConversationKeyFactory())->create($request);

		self::assertNotNull($scope);
		self::assertSame('conversation-1', $scope->getConversationId());
		self::assertSame($ownerKey, $scope->getOwnerKey());
		self::assertSame(64, strlen($scope->getConversationKey()));
	}

	public function testReturnsNullWithoutCompleteConversationContext(): void {
		$request = new AgentExecutionRequest([], [], [
			'conversation_id' => 'conversation-1'
		]);

		self::assertNull((new NeuronConversationKeyFactory())->create($request));
	}
}
