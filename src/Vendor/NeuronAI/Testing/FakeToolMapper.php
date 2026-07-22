<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Testing;

use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ProviderToolInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use function array_map;
class FakeToolMapper implements ToolMapperInterface
{
    /**
     * @param array<ToolInterface|ProviderToolInterface> $tools
     * @return array<array<string, mixed>>
     */
    public function map(array $tools): array
    {
        return array_map(static fn(ToolInterface|ProviderToolInterface $tool): array => $tool->jsonSerialize(), $tools);
    }
}
