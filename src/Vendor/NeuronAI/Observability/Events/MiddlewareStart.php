<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
use NeuronAi\Vendor\NeuronAI\Workflow\Middleware\WorkflowMiddleware;
class MiddlewareStart
{
    public function __construct(public WorkflowMiddleware $middleware, public Event $event)
    {
    }
}
