<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

class BranchStart
{
    public function __construct(public readonly string $branchId)
    {
    }
}
