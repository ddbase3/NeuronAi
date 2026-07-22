<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Evaluation\Dataset;

use NeuronAi\Vendor\NeuronAI\Evaluation\Contracts\DatasetInterface;
class ArrayDataset implements DatasetInterface
{
    /**
     * @param array<array<string, mixed>> $data
     */
    public function __construct(private readonly array $data)
    {
    }
    /**
     * @return array<array<string, mixed>>
     */
    public function load(): array
    {
        return $this->data;
    }
}
