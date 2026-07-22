<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Evaluation\Assertions;

use NeuronAi\Vendor\NeuronAI\Evaluation\AssertionResult;
use NeuronAi\Vendor\NeuronAI\Exceptions\VectorStoreException;
use NeuronAi\Vendor\NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAi\Vendor\NeuronAI\RAG\VectorSimilarity;
use function gettype;
use function is_string;
class StringSimilarity extends AbstractAssertion
{
    public function __construct(protected string $reference, protected EmbeddingsProviderInterface $embeddingsProvider, protected float $threshold = 0.6)
    {
    }
    /**
     * @throws VectorStoreException
     */
    public function evaluate(mixed $actual): AssertionResult
    {
        if (!is_string($actual)) {
            return AssertionResult::fail(0.0, 'Expected actual value to be a string, got ' . gettype($actual));
        }
        $actualEmbeddings = $this->embeddingsProvider->embedText($actual);
        $referenceEmbeddings = $this->embeddingsProvider->embedText($this->reference);
        $score = VectorSimilarity::cosineSimilarity($actualEmbeddings, $referenceEmbeddings);
        if ($score >= $this->threshold) {
            return AssertionResult::pass($score);
        }
        return AssertionResult::fail($score, "Expected '{$actual}' to be similar to '{$this->reference}' (threshold: '{$this->threshold}')");
    }
}
