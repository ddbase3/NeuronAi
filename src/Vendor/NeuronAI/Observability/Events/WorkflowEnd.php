<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Workflow\WorkflowState;
class WorkflowEnd
{
    public function __construct(public WorkflowState $state)
    {
    }
}
