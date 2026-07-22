<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent;

use NeuronAi\Vendor\NeuronAI\Chat\History\AbstractChatHistory;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use NeuronAi\Vendor\NeuronAI\Tools\Toolkits\ToolkitInterface;
use NeuronAi\Vendor\NeuronAI\Workflow\Interrupt\InterruptRequest;
interface AgentInterface
{
    public function setAiProvider(AIProviderInterface $provider): AgentInterface;
    public function resolveProvider(): AIProviderInterface;
    public function setInstructions(string $instructions): AgentInterface;
    public function resolveInstructions(): string;
    /**
     * @param ToolInterface|ToolInterface[]|ToolkitInterface $tools
     */
    public function addTool(ToolInterface|ToolkitInterface|array $tools): AgentInterface;
    /**
     * @return ToolInterface[]
     */
    public function getTools(): array;
    public function setChatHistory(AbstractChatHistory $chatHistory): AgentInterface;
    /**
     * @param Message|Message[] $messages
     */
    public function chat(Message|array $messages = [], ?InterruptRequest $interrupt = null): AgentHandler;
    /**
     * @param Message|Message[] $messages
     */
    public function stream(Message|array $messages = [], ?InterruptRequest $interrupt = null): AgentHandler;
    /**
     * @param Message|Message[] $messages
     */
    public function structured(Message|array $messages = [], ?string $class = null, int $maxRetries = 1, ?InterruptRequest $interrupt = null): mixed;
}
