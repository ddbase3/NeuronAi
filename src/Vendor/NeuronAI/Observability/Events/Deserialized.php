<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

class Deserialized
{
    public function __construct(public string $class)
    {
    }
}
