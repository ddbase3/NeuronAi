<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Splitter;

use NeuronAi\Vendor\NeuronAI\RAG\Document;
abstract class AbstractSplitter implements SplitterInterface
{
    /**
     * @param  Document[]  $documents
     * @return Document[]
     */
    public function splitDocuments(array $documents): array
    {
        $split = [];
        foreach ($documents as $document) {
            $split = [...$split, ...$this->splitDocument($document)];
        }
        return $split;
    }
}
