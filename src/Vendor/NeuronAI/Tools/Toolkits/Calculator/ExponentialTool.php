<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\Calculator;

use NeuronAi\Vendor\NeuronAI\Tools\PropertyType;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use NeuronAi\Vendor\NeuronAI\Tools\ToolProperty;
class ExponentialTool extends Tool
{
    public function __construct()
    {
        parent::__construct(name: 'calculate_exponential', description: 'Calculate the exponential between two numbers and return the result');
    }
    public function properties(): array
    {
        return [ToolProperty::make(name: 'number', type: PropertyType::NUMBER, description: 'The base number to exponentiate', required: \true), ToolProperty::make(name: 'exponent', type: PropertyType::NUMBER, description: 'The exponent', required: \true)];
    }
    public function __invoke(int|float $number, int|float $exponent): int|float
    {
        return $number ** $exponent;
    }
}
