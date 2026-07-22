<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use Throwable;
class AgentError
{
    public function __construct(public Throwable $exception, public bool $unhandled = \true)
    {
    }
}
