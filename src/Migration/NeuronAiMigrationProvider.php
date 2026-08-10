<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 **********************************************************************/

namespace NeuronAi\Migration;

use Base3\Migration\Api\IDatabaseMigrationProvider;

final class NeuronAiMigrationProvider implements IDatabaseMigrationProvider {

	public static function getName(): string {
		return 'neuronaimigrationprovider';
	}

	public function isActive(): bool {
		return true;
	}

	public function getMigrations(): array {
		return [Migration2026080701ConversationMetadata::class];
	}
}
