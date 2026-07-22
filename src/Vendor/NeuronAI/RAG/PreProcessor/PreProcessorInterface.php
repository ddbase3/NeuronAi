<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\PreProcessor;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
interface PreProcessorInterface
{
    /**
     * Process and return the question.
     *
     * @param Message $question The question to process.
     * @return Message The processed question.
     */
    public function process(Message $question): Message;
}
