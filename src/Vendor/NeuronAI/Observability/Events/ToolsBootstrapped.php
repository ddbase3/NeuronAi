<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
class ToolsBootstrapped
{
    /**
     * @param ToolInterface[] $tools
     * @param string[] $guidelines
     */
    public function __construct(public array $tools, public array $guidelines = [])
    {
    }
}
