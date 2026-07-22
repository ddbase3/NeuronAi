<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers;

use NeuronAi\Vendor\NeuronAI\Tools\ProviderToolInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
interface ToolMapperInterface
{
    /**
     * @param array<ToolInterface|ProviderToolInterface> $tools
     */
    public function map(array $tools): array;
}
