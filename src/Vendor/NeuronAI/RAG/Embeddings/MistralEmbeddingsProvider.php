<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Embeddings;

class MistralEmbeddingsProvider extends OpenAIEmbeddingsProvider
{
    protected string $baseUri = 'https://api.mistral.ai/v1';
}
