<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\StructuredOutput\Validation;

interface ValidationRuleInterface
{
    public function validate(string $name, mixed $value, array &$violations): void;
}
