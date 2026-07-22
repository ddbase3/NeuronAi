<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\Jina;

use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use NeuronAi\Vendor\NeuronAI\Tools\Toolkits\AbstractToolkit;
/**
 * @method static static make(string $key)
 */
class JinaToolkit extends AbstractToolkit
{
    public function __construct(protected string $key)
    {
    }
    /**
     * @return array<Tool>
     */
    public function provide(): array
    {
        return [new JinaWebSearch($this->key), new JinaUrlReader($this->key)];
    }
}
