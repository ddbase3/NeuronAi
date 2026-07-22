<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Evaluation\Contracts;

interface DatasetInterface
{
    /**
     * Load dataset from source
     * @return array<array<string, mixed>> Array of dataset items
     */
    public function load(): array;
}
