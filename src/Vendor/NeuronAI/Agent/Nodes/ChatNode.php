<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent\Nodes;

use NeuronAi\Vendor\Inspector\Exceptions\InspectorException;
use NeuronAi\Vendor\NeuronAI\Agent\AgentState;
use NeuronAi\Vendor\NeuronAI\Agent\ChatHistoryHelper;
use NeuronAi\Vendor\NeuronAI\Agent\Events\AIInferenceEvent;
use NeuronAi\Vendor\NeuronAI\Agent\Events\ToolCallEvent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InferenceStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InferenceStop;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\StopEvent;
use NeuronAi\Vendor\NeuronAI\Workflow\Node;
/**
 * Receives an AIInferenceEvent containing instructions and tools that middleware can
 * modify before the actual inference call is made.
 */
class ChatNode extends Node
{
    use ChatHistoryHelper;
    public function __construct(protected AIProviderInterface $provider)
    {
    }
    /**
     * @throws InspectorException
     */
    public function __invoke(AIInferenceEvent $event, AgentState $state): StopEvent|ToolCallEvent
    {
        $this->addToChatHistory($state, $event->getMessages());
        $chatHistory = $state->getChatHistory();
        $lastMessage = $chatHistory->getLastMessage();
        $this->emit('inference-start', new InferenceStart($lastMessage));
        $response = $this->inference($event, $chatHistory->getMessages());
        $this->emit('inference-stop', new InferenceStop($lastMessage, $response));
        // If the response is a tool call, route to the tool node.
        // It will be responsible to add the tool call message to the chat history.
        if ($response instanceof ToolCallMessage) {
            return new ToolCallEvent($response, $event);
        }
        // Add the final response to chat history (after tool loop)
        $this->addToChatHistory($state, $response);
        return new StopEvent();
    }
    /**
     * Perform the actual inference call to the AI provider.
     *
     * This method is extracted to allow easy customization of the inference behavior.
     * Subclasses can override this method to:
     * - Use async operations (chatAsync with Amp, ReactPHP, etc.)
     * - Add custom retry logic
     * - Implement caching
     * - Add custom error handling
     *
     * @param Message[] $messages
     */
    protected function inference(AIInferenceEvent $event, array $messages): Message
    {
        return $this->provider->systemPrompt($event->instructions)->setTools($event->tools)->chat(...$messages);
    }
}
