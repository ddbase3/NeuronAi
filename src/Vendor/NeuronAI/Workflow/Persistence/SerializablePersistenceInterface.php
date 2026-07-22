<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Workflow\Persistence;

use NeuronAi\Vendor\NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
/**
 * todo: Merge with PersistenceInterface on the next major version v4
 */
interface SerializablePersistenceInterface
{
    public function serialize(WorkflowInterrupt $interrupt): string;
    public function unserialize(string $data): WorkflowInterrupt;
}
