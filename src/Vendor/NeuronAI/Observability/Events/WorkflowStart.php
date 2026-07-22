<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Workflow\NodeInterface;
class WorkflowStart
{
    /**
     * @param NodeInterface[] $eventNodeMap
     */
    public function __construct(public array $eventNodeMap)
    {
    }
}
