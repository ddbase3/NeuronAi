<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\DataLoader;

use NeuronAi\Vendor\NeuronAI\RAG\Document;
interface DataLoaderInterface
{
    /**
     * @return Document[]
     */
    public function getDocuments(): array;
}
