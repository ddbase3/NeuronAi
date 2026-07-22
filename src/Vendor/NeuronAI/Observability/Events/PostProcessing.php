<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\RAG\Document;
class PostProcessing
{
    /**
     * @param Document[] $documents
     */
    public function __construct(public string $processor, public Message $question, public array $documents)
    {
    }
}
