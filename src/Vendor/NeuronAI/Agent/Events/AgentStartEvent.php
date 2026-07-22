<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
class AgentStartEvent implements Event
{
    /**
     * @var Message[]
     */
    protected array $messages = [];
    public function setMessages(Message ...$messages): void
    {
        $this->messages = $messages;
    }
    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
