<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
/**
 * Event triggered when the AI provider requests tool execution.
 */
class ToolCallEvent implements Event
{
    public function __construct(public readonly ToolCallMessage $toolCallMessage, public readonly AIInferenceEvent $inferenceEvent)
    {
    }
}
