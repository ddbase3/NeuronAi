<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 **********************************************************************/

namespace NeuronAi\Service;

use Base3\Database\Api\IDatabase;

/**
 * Owns the private Neuron chat-history table definition.
 */
final class NeuronChatHistorySchema {

	public const TABLE = 'base3_neuronai_chathistory';

	public static function ensureTable(IDatabase $database): void {
		$database->connect();
		$database->nonQuery('
			CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`conversation_key` CHAR(64) NOT NULL,
				`conversation_id` VARCHAR(100) NOT NULL,
				`owner_key` CHAR(64) NOT NULL,
				`config_group` VARCHAR(191) NOT NULL,
				`config_name` VARCHAR(191) NOT NULL,
				`runtime_id` VARCHAR(64) NOT NULL,
				`title` VARCHAR(255) NOT NULL DEFAULT \'New conversation\',
				`title_source` VARCHAR(32) NOT NULL DEFAULT \'temporary\',
				`opening_message` TEXT NULL,
				`messages` LONGTEXT NOT NULL,
				`message_count` INT UNSIGNED NOT NULL DEFAULT 0,
				`version` INT UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				`last_accessed_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_neuronai_conversation` (`conversation_key`),
				KEY `idx_neuronai_owner_updated` (`owner_key`, `updated_at`),
				KEY `idx_neuronai_chatbot_updated` (`config_group`, `config_name`, `updated_at`),
				KEY `idx_neuronai_conversation_list` (`owner_key`, `config_group`, `config_name`, `last_accessed_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		');
	}
}
