<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

class InstructionsChanged
{
    public function __construct(public string $previous, public string $current)
    {
    }
}
