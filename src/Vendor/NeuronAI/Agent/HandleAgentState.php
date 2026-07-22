<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent;

use NeuronAi\Vendor\NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAi\Vendor\NeuronAI\Chat\History\InMemoryChatHistory;
trait HandleAgentState
{
    protected function state(): AgentState
    {
        $state = new AgentState();
        $state->setChatHistory($this->chatHistory());
        return $state;
    }
    protected function chatHistory(): ChatHistoryInterface
    {
        return new InMemoryChatHistory();
    }
    public function setChatHistory(ChatHistoryInterface $chatHistory): self
    {
        /** @var AgentState $state */
        $state = $this->resolveState();
        $state->setChatHistory($chatHistory);
        return $this;
    }
    public function getChatHistory(): ChatHistoryInterface
    {
        /** @var AgentState $state */
        $state = $this->resolveState();
        return $state->getChatHistory();
    }
}
