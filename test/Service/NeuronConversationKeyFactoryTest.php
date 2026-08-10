<?php declare(strict_types=1);

namespace NeuronAiTest\Service;

use AssistantFoundation\Dto\AgentExecutionRequest;
use NeuronAi\Service\NeuronConversationKeyFactory;
use PHPUnit\Framework\TestCase;

final class NeuronConversationKeyFactoryTest extends TestCase {

	public function testBuildsStableScopeFromServerOwnedIdentity(): void {
		$ownerKey = str_repeat('a', 64);
		$request = new AgentExecutionRequest([], [], [
			'conversation_id' => 'conversation-1',
			'chatbot_config_group' => 'chatbot',
			'chatbot_config_name' => 'example'
		]);

		$scope = (new NeuronConversationKeyFactory())->create($request, $ownerKey);

		self::assertNotNull($scope);
		self::assertSame('conversation-1', $scope->getConversationId());
		self::assertSame($ownerKey, $scope->getOwnerKey());
		self::assertSame(64, strlen($scope->getConversationKey()));
	}

	public function testReturnsNullWithoutCompleteConversationContext(): void {
		$request = new AgentExecutionRequest([], [], [
			'conversation_id' => 'conversation-1'
		]);

		self::assertNull((new NeuronConversationKeyFactory())->create($request, str_repeat('a', 64)));
	}

	public function testReturnsNullForInvalidOwnerIdentity(): void {
		$request = new AgentExecutionRequest([], [], [
			'conversation_id' => 'conversation-1',
			'chatbot_config_group' => 'chatbot',
			'chatbot_config_name' => 'example'
		]);

		self::assertNull((new NeuronConversationKeyFactory())->create($request, 'client-owner'));
	}

	public function testCreatesSameScopeFromExplicitCanonicalValues(): void {
		$factory = new NeuronConversationKeyFactory();
		$scope = $factory->createFromValues(
			'conversation-1',
			str_repeat('b', 64),
			'chatbot',
			'example'
		);

		self::assertNotNull($scope);
		self::assertSame('conversation-1', $scope->getConversationId());
		self::assertSame(str_repeat('b', 64), $scope->getOwnerKey());
		self::assertSame('chatbot', $scope->getConfigGroup());
		self::assertSame('example', $scope->getConfigName());
	}
}
