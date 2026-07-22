<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Testing;

use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
use NeuronAi\Vendor\NeuronAI\Workflow\NodeInterface;
use NeuronAi\Vendor\NeuronAI\Workflow\WorkflowState;
class MiddlewareRecord
{
    /**
     * @param string $method The method called: 'before' or 'after'
     * @param NodeInterface $node The node being executed
     * @param Event $event The event passed to the middleware
     * @param WorkflowState $state The workflow state at call time
     */
    public function __construct(public readonly string $method, public readonly NodeInterface $node, public readonly Event $event, public readonly WorkflowState $state)
    {
    }
}
