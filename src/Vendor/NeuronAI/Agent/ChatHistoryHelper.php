<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent;

use NeuronAi\Vendor\Inspector\Exceptions\InspectorException;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MessageSaved;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MessageSaving;
use function is_array;
trait ChatHistoryHelper
{
    /**
     * @throws InspectorException
     */
    protected function addToChatHistory(AgentState $state, Message|array $messages): void
    {
        $messages = is_array($messages) ? $messages : [$messages];
        foreach ($messages as $message) {
            $this->emit('message-saving', new MessageSaving($message));
            $state->getChatHistory()->addMessage($message);
            $this->emit('message-saved', new MessageSaved($message));
            $state->addStep($message);
        }
    }
}
