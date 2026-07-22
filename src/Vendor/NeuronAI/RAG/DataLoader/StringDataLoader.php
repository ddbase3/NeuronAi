<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\DataLoader;

use NeuronAi\Vendor\NeuronAI\RAG\Document;
class StringDataLoader extends AbstractDataLoader
{
    public function __construct(protected string $content)
    {
        parent::__construct();
    }
    public function getDocuments(): array
    {
        return $this->splitter->splitDocument(new Document($this->content));
    }
}
