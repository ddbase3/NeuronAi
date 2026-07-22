<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
/**
 * Event that triggers RAG preprocessing.
 *
 * Emitted by PrepareRAGNode to initiate the RAG pipeline.
 */
class QueryPreProcessEvent implements Event
{
    public function __construct(public readonly Message $query)
    {
    }
}
