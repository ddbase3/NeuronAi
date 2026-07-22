<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Workflow;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Adapters\StreamAdapterInterface;
use Generator;
interface WorkflowHandlerInterface
{
    public function events(?StreamAdapterInterface $adapter = null): Generator;
    public function run(): WorkflowState;
}
