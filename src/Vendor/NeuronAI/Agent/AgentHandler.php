<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Agent;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Exceptions\WorkflowException;
use NeuronAi\Vendor\NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAi\Vendor\NeuronAI\Workflow\WorkflowHandler;
use Throwable;
class AgentHandler extends WorkflowHandler
{
    /**
     * Agent convenience method
     *
     * @throws Throwable
     * @throws WorkflowException
     * @throws WorkflowInterrupt
     */
    public function getMessage(): Message
    {
        /** @var AgentState $state */
        $state = $this->run();
        // Blocks until complete
        return $state->getMessage();
    }
}
