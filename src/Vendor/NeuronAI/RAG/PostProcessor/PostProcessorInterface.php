<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\PostProcessor;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\RAG\Document;
interface PostProcessorInterface
{
    /**
     * Process an array of documents and return the processed documents.
     *
     * @param Message $question The question to process the documents for.
     * @param Document[] $documents The documents to process.
     * @return Document[] The processed documents.
     */
    public function process(Message $question, array $documents): array;
}
