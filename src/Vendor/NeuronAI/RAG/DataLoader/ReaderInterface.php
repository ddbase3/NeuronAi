<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\DataLoader;

interface ReaderInterface
{
    public static function getText(string $filePath, array $options = []): string;
}
