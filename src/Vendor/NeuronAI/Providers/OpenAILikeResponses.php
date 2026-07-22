<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers;

use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;
class OpenAILikeResponses extends OpenAIResponses
{
    public function __construct(protected string $baseUri, protected string $key, protected string $model, protected array $parameters = [], protected bool $strict_response = \false, ?HttpClientInterface $httpClient = null)
    {
        parent::__construct($key, $model, $parameters, $strict_response, $httpClient);
    }
}
