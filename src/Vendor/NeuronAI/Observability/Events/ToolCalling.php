<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
class ToolCalling
{
    public function __construct(public ToolInterface $tool, public readonly bool $fork = \false)
    {
    }
}
