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

use Base3\Accesscontrol\Api\IAccesscontrol;
use Base3\Session\Api\ISession;

/**
 * Resolves the same server-owned conversation owner identity used by Chatbot turns.
 */
final class NeuronConversationOwnerResolver {

	public function __construct(
		private readonly IAccesscontrol $accesscontrol,
		private readonly ISession $session
	) {}

	public static function getName(): string {
		return 'neuronconversationownerresolver';
	}

	public function resolveOwnerKey(): string {
		return hash('sha256', $this->resolveOwnerIdentity());
	}

	private function resolveOwnerIdentity(): string {
		$userId = trim((string)($this->accesscontrol->getUserId() ?? ''));
		if ($userId !== '' && $userId !== '0') {
			return 'user:' . $userId;
		}

		if (!$this->session->started() && !$this->session->start()) {
			throw new \RuntimeException('Neuron AI conversation could not start the session.');
		}
		$sessionId = trim($this->session->getId());
		if ($sessionId === '') {
			throw new \RuntimeException('Neuron AI conversation requires an active user or session.');
		}

		return 'session:' . $sessionId;
	}
}
