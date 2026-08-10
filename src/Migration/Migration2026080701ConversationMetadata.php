<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 **********************************************************************/

namespace NeuronAi\Migration;

use Base3\Database\Api\IDatabase;
use Base3\Migration\Api\IDatabaseMigration;
use NeuronAi\Service\NeuronChatHistorySchema;

final class Migration2026080701ConversationMetadata implements IDatabaseMigration {

	public function __construct(private readonly IDatabase $database) {}

	public static function getName(): string {
		return 'neuronai2026080701conversationmetadata';
	}

	public function getVersion(): string {
		return '2026080701';
	}

	public function getDescription(): string {
		return 'Add conversation-list metadata to Neuron chat history.';
	}

	public function up(): void {
		NeuronChatHistorySchema::ensureTable($this->database);

		$this->addColumn('title', "VARCHAR(255) NOT NULL DEFAULT 'New conversation' AFTER `runtime_id`");
		$this->addColumn('title_source', "VARCHAR(32) NOT NULL DEFAULT 'temporary' AFTER `title`");
		$this->addColumn('opening_message', 'TEXT NULL AFTER `title_source`');
		$this->addIndex(
			'idx_neuronai_conversation_list',
			'(`owner_key`, `config_group`, `config_name`, `last_accessed_at`)'
		);
	}

	private function addColumn(string $name, string $definition): void {
		if (is_array($this->database->singleQuery(
			'SHOW COLUMNS FROM `' . NeuronChatHistorySchema::TABLE . '` LIKE ' . $this->quote($name)
		))) {
			return;
		}

		$this->database->nonQuery(
			'ALTER TABLE `' . NeuronChatHistorySchema::TABLE . '` ADD COLUMN `' . $name . '` ' . $definition
		);
	}

	private function addIndex(string $name, string $columns): void {
		if (is_array($this->database->singleQuery(
			'SHOW INDEX FROM `' . NeuronChatHistorySchema::TABLE . '` WHERE `Key_name` = ' . $this->quote($name)
		))) {
			return;
		}

		$this->database->nonQuery(
			'ALTER TABLE `' . NeuronChatHistorySchema::TABLE . '` ADD INDEX `' . $name . '` ' . $columns
		);
	}

	private function quote(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}
}
