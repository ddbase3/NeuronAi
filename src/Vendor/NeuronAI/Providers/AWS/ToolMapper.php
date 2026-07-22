<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\AWS;

use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ProviderToolInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolPropertyInterface;
use stdClass;
use function array_reduce;
use function array_merge;
class ToolMapper implements ToolMapperInterface
{
    public function map(array $tools): array
    {
        $mapping = [];
        foreach ($tools as $tool) {
            $mapping[] = match (\true) {
                $tool instanceof ToolInterface => $this->mapTool($tool),
                $tool instanceof ProviderToolInterface => throw new ProviderException('Bedrock Runtime does not support Provider Tools'),
                default => throw new ProviderException('Could not map tool type ' . $tool::class),
            };
        }
        return $mapping;
    }
    protected function mapTool(ToolInterface $tool): array
    {
        $payload = ['toolSpec' => ['name' => $tool->getName(), 'description' => $tool->getDescription(), 'inputSchema' => ['json' => ['type' => 'object', 'properties' => new stdClass(), 'required' => []]]]];
        $properties = array_reduce($tool->getProperties(), function (array $carry, ToolPropertyInterface $property): array {
            $carry[$property->getName()] = $property->getJsonSchema();
            return $carry;
        }, []);
        if (!empty($properties)) {
            $payload['toolSpec']['inputSchema']['json'] = ['type' => 'object', 'properties' => $properties, 'required' => $tool->getRequiredProperties()];
        }
        if ($tool->getParameters() !== []) {
            $payload['toolSpec'] = array_merge($payload['toolSpec'], $tool->getParameters());
        }
        return $payload;
    }
}
