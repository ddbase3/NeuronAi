<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent\Nodes;

use NeuronAi\Vendor\NeuronAI\Agent\AgentState;
use NeuronAi\Vendor\NeuronAI\Agent\ChatHistoryHelper;
use NeuronAi\Vendor\NeuronAI\Agent\Events\AIInferenceEvent;
use NeuronAi\Vendor\NeuronAI\Agent\Events\ToolCallEvent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Observability\Events\AgentError;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InferenceStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InferenceStop;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\StopEvent;
use NeuronAi\Vendor\NeuronAI\Workflow\Node;
use Generator;
use Throwable;
class StreamingNode extends Node
{
    use ChatHistoryHelper;
    public function __construct(protected AIProviderInterface $provider)
    {
    }
    /**
     * @throws Throwable
     */
    public function __invoke(AIInferenceEvent $event, AgentState $state): Generator|ToolCallEvent
    {
        $this->addToChatHistory($state, $event->getMessages());
        $chatHistory = $state->getChatHistory();
        $lastMessage = $chatHistory->getLastMessage();
        $this->emit('inference-start', new InferenceStart($lastMessage));
        try {
            $stream = $this->provider->systemPrompt($event->instructions)->setTools($event->tools)->stream(...$chatHistory->getMessages());
            // Yield all chunks as-is (TextChunk, ReasoningChunk, etc.)
            foreach ($stream as $chunk) {
                yield $chunk;
            }
            // Get the final message from the generator return value
            $message = $stream->getReturn();
            $this->emit('inference-stop', new InferenceStop($lastMessage, $message));
            // Route based on the message type
            if ($message instanceof ToolCallMessage) {
                return new ToolCallEvent($message, $event);
            }
            // Add the final message to the chat history (after tool loop)
            $this->addToChatHistory($state, $message);
            return new StopEvent();
        } catch (Throwable $exception) {
            $this->emit('error', new AgentError($exception));
            throw $exception;
        }
    }
}
