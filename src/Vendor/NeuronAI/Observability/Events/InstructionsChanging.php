<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

class InstructionsChanging
{
    public function __construct(public string $instructions)
    {
    }
}
