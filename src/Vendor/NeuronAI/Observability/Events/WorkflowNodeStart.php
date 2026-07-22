<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Workflow\WorkflowState;
class WorkflowNodeStart
{
    public function __construct(public string $node, public WorkflowState $state)
    {
    }
}
