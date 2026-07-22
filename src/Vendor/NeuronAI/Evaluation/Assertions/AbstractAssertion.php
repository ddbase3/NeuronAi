<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Evaluation\Assertions;

use NeuronAi\Vendor\NeuronAI\Evaluation\Contracts\AssertionInterface;
use ReflectionClass;
abstract class AbstractAssertion implements AssertionInterface
{
    public function getName(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }
}
