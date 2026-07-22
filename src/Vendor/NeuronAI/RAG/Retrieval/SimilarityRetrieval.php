<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Retrieval;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAi\Vendor\NeuronAI\RAG\VectorStore\VectorStoreInterface;
class SimilarityRetrieval implements RetrievalInterface
{
    public function __construct(protected readonly VectorStoreInterface $vectorStore, protected readonly EmbeddingsProviderInterface $embeddingProvider)
    {
    }
    public function retrieve(Message $query): array
    {
        return $this->vectorStore->similaritySearch($this->embeddingProvider->embedText($query->getContent()));
    }
}
