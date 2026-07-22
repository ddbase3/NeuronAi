<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Retrieval;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\RAG\Document;
interface RetrievalInterface
{
    /**
     * Retrieve relevant documents for the given query.
     *
     * @return Document[]
     */
    public function retrieve(Message $query): array;
}
