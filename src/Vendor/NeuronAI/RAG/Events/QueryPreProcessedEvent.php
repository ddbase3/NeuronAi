<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
/**
 * Event emitted after query preprocessing.
 *
 * Triggers document retrieval from vector store.
 */
class QueryPreProcessedEvent implements Event
{
    public function __construct(public readonly Message $query)
    {
    }
}
