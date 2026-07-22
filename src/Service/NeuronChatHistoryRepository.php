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

use Base3\Database\Api\IDatabase;
use NeuronAi\Dto\NeuronChatHistoryRecord;
use NeuronAi\Dto\NeuronConversationScope;
use RuntimeException;

/**
 * Persists Neuron-native serialized chat histories in a dedicated table.
 */
final class NeuronChatHistoryRepository {

	private const TABLE = 'base3_neuronai_chathistory';

	private bool $schemaEnsured = false;

	public function __construct(private readonly IDatabase $database) {}

	public static function getName(): string {
		return 'neuronchathistoryrepository';
	}

	public function loadOrCreate(NeuronConversationScope $scope): NeuronChatHistoryRecord {
		$this->ensureSchema();
		$now = date('Y-m-d H:i:s');

		$this->database->nonQuery(
			'INSERT INTO `' . self::TABLE . '` ('
			. '`conversation_key`, `conversation_id`, `owner_key`, `config_group`, `config_name`, '
			. '`runtime_id`, `messages`, `message_count`, `version`, `created_at`, `updated_at`, `last_accessed_at`'
			. ') VALUES ('
			. $this->quote($scope->getConversationKey()) . ', '
			. $this->quote($scope->getConversationId()) . ', '
			. $this->quote($scope->getOwnerKey()) . ', '
			. $this->quote($scope->getConfigGroup()) . ', '
			. $this->quote($scope->getConfigName()) . ', '
			. $this->quote('neuronai') . ', '
			. $this->quote('[]') . ', 0, 0, '
			. $this->quote($now) . ', '
			. $this->quote($now) . ', '
			. $this->quote($now)
			. ') ON DUPLICATE KEY UPDATE `last_accessed_at` = VALUES(`last_accessed_at`)'
		);

		$record = $this->load($scope->getConversationKey());
		$messages = $record->getMessages();
		$repairedMessages = $this->removeIncompleteTail($messages);
		if (count($repairedMessages) !== count($messages)) {
			$version = $this->save(
				$scope->getConversationKey(),
				$record->getVersion(),
				$repairedMessages,
				$messages
			);

			return new NeuronChatHistoryRecord($repairedMessages, $version);
		}

		return $record;
	}

	/**
	 * @param array<int,mixed> $messages
	 * @param array<int,array<string,mixed>>|null $expectedMessages
	 */
	public function save(
		string $conversationKey,
		int $version,
		array $messages,
		?array $expectedMessages = null
	): int {
		$this->ensureSchema();
		if ($this->update($conversationKey, $version, $messages)) {
			return $version + 1;
		}

		$current = $this->load($conversationKey);
		if ($expectedMessages === null || $current->getMessages() !== array_values($expectedMessages)) {
			throw new RuntimeException('Neuron chat history was modified by another request.');
		}

		$currentVersion = $current->getVersion();
		if (!$this->update($conversationKey, $currentVersion, $messages)) {
			throw new RuntimeException('Neuron chat history was modified by another request.');
		}

		return $currentVersion + 1;
	}

	public function clear(string $conversationKey, int $version): int {
		return $this->save($conversationKey, $version, []);
	}

	/** @param array<int,mixed> $messages */
	private function update(string $conversationKey, int $version, array $messages): bool {
		$json = json_encode(
			$messages,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
		);
		$now = date('Y-m-d H:i:s');

		$this->database->nonQuery(
			'UPDATE `' . self::TABLE . '` SET '
			. '`messages` = ' . $this->quote($json) . ', '
			. '`message_count` = ' . count($messages) . ', '
			. '`version` = `version` + 1, '
			. '`updated_at` = ' . $this->quote($now) . ', '
			. '`last_accessed_at` = ' . $this->quote($now) . ' '
			. 'WHERE `conversation_key` = ' . $this->quote($conversationKey) . ' '
			. 'AND `version` = ' . max(0, $version) . ' LIMIT 1'
		);

		return $this->database->affectedRows() === 1;
	}

	private function load(string $conversationKey): NeuronChatHistoryRecord {
		$row = $this->database->singleQuery(
			'SELECT `messages`, `version` FROM `' . self::TABLE . '` '
			. 'WHERE `conversation_key` = ' . $this->quote($conversationKey) . ' LIMIT 1'
		);
		if (!is_array($row)) {
			throw new RuntimeException('Neuron chat history could not be loaded.');
		}

		$messages = json_decode((string)($row['messages'] ?? '[]'), true);
		if (!is_array($messages)) {
			throw new RuntimeException('Neuron chat history contains invalid JSON.');
		}
		foreach ($messages as $message) {
			if (!is_array($message)) {
				throw new RuntimeException('Neuron chat history contains an invalid message record.');
			}
		}

		return new NeuronChatHistoryRecord(
			array_values($messages),
			max(0, (int)($row['version'] ?? 0))
		);
	}

	/**
	 * Removes a trailing partial turn left by an older failed or cancelled run.
	 * A persisted completed Neuron turn always ends with a regular assistant
	 * message. Tool call and tool result messages are intermediate workflow
	 * state and are therefore removed together with an incomplete tail.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	private function removeIncompleteTail(array $messages): array {
		while ($messages !== []) {
			$last = $messages[array_key_last($messages)];
			$role = strtolower(trim((string)($last['role'] ?? '')));
			$type = strtolower(trim((string)($last['type'] ?? '')));
			if ($role === 'assistant' && $type !== 'tool_call') {
				break;
			}
			array_pop($messages);
		}

		return array_values($messages);
	}

	private function ensureSchema(): void {
		if ($this->schemaEnsured) {
			return;
		}

		$this->database->connect();
		$this->database->nonQuery('
			CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`conversation_key` CHAR(64) NOT NULL,
				`conversation_id` VARCHAR(100) NOT NULL,
				`owner_key` CHAR(64) NOT NULL,
				`config_group` VARCHAR(191) NOT NULL,
				`config_name` VARCHAR(191) NOT NULL,
				`runtime_id` VARCHAR(64) NOT NULL,
				`messages` LONGTEXT NOT NULL,
				`message_count` INT UNSIGNED NOT NULL DEFAULT 0,
				`version` INT UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				`last_accessed_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_neuronai_conversation` (`conversation_key`),
				KEY `idx_neuronai_owner_updated` (`owner_key`, `updated_at`),
				KEY `idx_neuronai_chatbot_updated` (`config_group`, `config_name`, `updated_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		');
		$this->schemaEnsured = true;
	}

	private function quote(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}
}
