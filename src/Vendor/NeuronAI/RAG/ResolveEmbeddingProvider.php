<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG;

use NeuronAi\Vendor\NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
trait ResolveEmbeddingProvider
{
    protected EmbeddingsProviderInterface $embeddingsProvider;
    public function setEmbeddingsProvider(EmbeddingsProviderInterface $provider): RAG
    {
        $this->embeddingsProvider = $provider;
        return $this;
    }
    protected function embeddings(): EmbeddingsProviderInterface
    {
        return $this->embeddingsProvider;
    }
    public function resolveEmbeddingsProvider(): EmbeddingsProviderInterface
    {
        return $this->embeddingsProvider ??= $this->embeddings();
    }
}
