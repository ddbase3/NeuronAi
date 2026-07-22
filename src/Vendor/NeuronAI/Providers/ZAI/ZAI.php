<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\ZAI;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ReasoningContent;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\Providers\HandleWithTools;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
class ZAI extends OpenAI
{
    use HasHttpClient;
    use HandleWithTools;
    use HandleStructured;
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(protected string $key, protected string $model, protected array $parameters = [], protected bool $strict_response = \false, ?HttpClientInterface $httpClient = null, protected string $baseUri = 'https://api.z.ai/api/paas/v4')
    {
        parent::__construct($key, $model, $parameters, $strict_response, $httpClient);
    }
    public function messageMapper(): MessageMapperInterface
    {
        return $this->messageMapper ??= new MessageMapper();
    }
    public function toolPayloadMapper(): ToolMapperInterface
    {
        return $this->toolPayloadMapper ??= new ToolMapper();
    }
    protected function createAssistantMessage(array $message): AssistantMessage
    {
        $response = new AssistantMessage($message['content']);
        if (isset($message['reasoning_content'])) {
            $response->addContent(new ReasoningContent($message['reasoning_content']));
        }
        return $response;
    }
}
