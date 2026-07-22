<?php declare(strict_types=1);

namespace NeuronAiTest\Service;

use Base3\Database\Api\IDatabase;
use NeuronAi\Chat\History\DatabaseNeuronChatHistory;
use NeuronAi\Dto\NeuronConversationScope;
use NeuronAi\Service\NeuronChatHistoryRepository;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

final class DatabaseNeuronChatHistoryTest extends TestCase {

	public function testPersistsCompleteNeuronTurnOnCommit(): void {
		$database = new ChatHistoryDatabaseStub();
		$repository = new NeuronChatHistoryRepository($database);
		$scope = $this->createScope();

		$history = new DatabaseNeuronChatHistory(
			$scope->getConversationKey(),
			$repository,
			$repository->loadOrCreate($scope)
		);
		$history->addMessage(new UserMessage('Mein Projekt heißt Atlas.'));
		$history->addMessage(new AssistantMessage('Verstanden.'));

		self::assertCount(0, $repository->loadOrCreate($scope)->getMessages());

		$history->commit();
		$reloaded = new DatabaseNeuronChatHistory(
			$scope->getConversationKey(),
			$repository,
			$repository->loadOrCreate($scope)
		);
		$messages = $reloaded->getMessages();

		self::assertCount(2, $messages);
		self::assertSame('Mein Projekt heißt Atlas.', $messages[0]->getContent());
		self::assertSame('Verstanden.', $messages[1]->getContent());
	}

	public function testDiscardDoesNotPersistPartialUserTurn(): void {
		$database = new ChatHistoryDatabaseStub();
		$repository = new NeuronChatHistoryRepository($database);
		$scope = $this->createScope();

		$history = new DatabaseNeuronChatHistory(
			$scope->getConversationKey(),
			$repository,
			$repository->loadOrCreate($scope)
		);
		$history->addMessage(new UserMessage('Unvollständiger Turn'));
		$history->discard();

		self::assertCount(0, $repository->loadOrCreate($scope)->getMessages());
	}

	public function testRepairsPreviouslyPersistedIncompleteTail(): void {
		$database = new ChatHistoryDatabaseStub();
		$database->seed([
			(new UserMessage('Verwaiste User-Nachricht'))->jsonSerialize()
		], 3);
		$repository = new NeuronChatHistoryRepository($database);

		$record = $repository->loadOrCreate($this->createScope());

		self::assertSame([], $record->getMessages());
		self::assertSame(4, $record->getVersion());
		self::assertSame([], $database->getStoredMessages());
	}

	public function testRetriesCommitAfterEquivalentInternalVersionAdvance(): void {
		$database = new ChatHistoryDatabaseStub();
		$repository = new NeuronChatHistoryRepository($database);
		$scope = $this->createScope();
		$record = $repository->loadOrCreate($scope);
		$history = new DatabaseNeuronChatHistory(
			$scope->getConversationKey(),
			$repository,
			$record
		);

		$database->advanceVersionWithoutChangingMessages();
		$history->addMessage(new UserMessage('Mein Projekt heißt Atlas.'));
		$history->addMessage(new AssistantMessage('Verstanden.'));
		$history->commit();

		self::assertSame(2, $database->getVersion());
		self::assertCount(2, $database->getStoredMessages());
	}

	private function createScope(): NeuronConversationScope {
		return new NeuronConversationScope(
			str_repeat('a', 64),
			'conversation-1',
			str_repeat('b', 64),
			'chatbot',
			'example'
		);
	}
}

final class ChatHistoryDatabaseStub implements IDatabase {

	/** @var array<string,mixed>|null */
	private ?array $row = null;
	private int $affectedRows = 0;
	private bool $connected = false;

	/** @param array<int,array<string,mixed>> $messages */
	public function seed(array $messages, int $version): void {
		$this->row = [
			'messages' => (string)json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'version' => $version
		];
	}

	public function advanceVersionWithoutChangingMessages(): void {
		if ($this->row === null) {
			$this->row = ['messages' => '[]', 'version' => 0];
		}
		$this->row['version'] = (int)$this->row['version'] + 1;
	}

	/** @return array<int,array<string,mixed>> */
	public function getStoredMessages(): array {
		$messages = json_decode((string)($this->row['messages'] ?? '[]'), true);
		return is_array($messages) ? $messages : [];
	}

	public function getVersion(): int {
		return (int)($this->row['version'] ?? 0);
	}

	public function connect(): void { $this->connected = true; }
	public function connected(): bool { return $this->connected; }
	public function disconnect(): void { $this->connected = false; }
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	public function nonQuery(string $query): void {
		$this->affectedRows = 0;
		if (str_contains($query, 'CREATE TABLE IF NOT EXISTS')) {
			return;
		}
		if (str_starts_with(trim($query), 'INSERT INTO')) {
			if ($this->row === null) {
				$this->row = ['messages' => '[]', 'version' => 0];
			}
			$this->affectedRows = 1;
			return;
		}
		if (str_starts_with(trim($query), 'UPDATE')) {
			if ($this->row === null) {
				return;
			}
			if (preg_match('/AND `version` = (\d+)/', $query, $versionMatch) !== 1) {
				return;
			}
			if ((int)$versionMatch[1] !== (int)$this->row['version']) {
				return;
			}
			if (preg_match('/`messages` = \'((?:\\\\.|[^\'])*)\'/s', $query, $match) !== 1) {
				return;
			}
			$this->row['messages'] = stripslashes($match[1]);
			$this->row['version'] = (int)$this->row['version'] + 1;
			$this->affectedRows = 1;
		}
	}

	public function scalarQuery(string $query): mixed { return null; }
	public function singleQuery(string $query): ?array { return $this->row; }
	public function &listQuery(string $query): array { $result = []; return $result; }
	public function &multiQuery(string $query): array { $result = []; return $result; }
	public function affectedRows(): int { return $this->affectedRows; }
	public function insertId(): int|string { return 1; }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { return false; }
	public function errorNumber(): int { return 0; }
	public function errorMessage(): string { return ''; }
}
