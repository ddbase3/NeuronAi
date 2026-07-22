<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\XAI;

use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\OpenAI;
class Grok extends OpenAI
{
    protected string $baseUri = 'https://api.x.ai/v1/';
}
