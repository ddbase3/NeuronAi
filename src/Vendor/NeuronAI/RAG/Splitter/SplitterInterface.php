<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Splitter;

use NeuronAi\Vendor\NeuronAI\RAG\Document;
interface SplitterInterface
{
    /**
     * @return Document[]
     */
    public function splitDocument(Document $document): array;
    /**
     * @param  Document[]  $documents
     * @return Document[]
     */
    public function splitDocuments(array $documents): array;
}
