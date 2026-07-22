<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Ollama;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\HandleWithTools;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use function array_map;
use function array_values;
class Ollama implements AIProviderInterface
{
    use HasHttpClient;
    use HandleWithTools;
    use HandleChat;
    use HandleStream;
    use HandleStructured;
    protected ?string $system = null;
    protected MessageMapperInterface $messageMapper;
    protected ToolMapperInterface $toolPayloadMapper;
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        protected string $url,
        // http://localhost:11434/api
        protected string $model,
        protected array $parameters = [],
        ?HttpClientInterface $httpClient = null
    )
    {
        // Use provided client or create default Guzzle client
        // Provider always configures base URI
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->url);
    }
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }
    public function messageMapper(): MessageMapperInterface
    {
        return $this->messageMapper ??= new MessageMapper();
    }
    public function toolPayloadMapper(): ToolMapperInterface
    {
        return $this->toolPayloadMapper ??= new ToolMapper();
    }
    /**
     * @param array<string, mixed> $toolCalls
     * @throws ProviderException
     */
    protected function createToolCallMessage(array $toolCalls, array|string|null $content = null): ToolCallMessage
    {
        $tools = array_map(fn(array $item): ToolInterface => $this->findTool($item['function']['name'])->setInputs($item['function']['arguments']), $toolCalls);
        return new ToolCallMessage($content, array_values($tools));
    }
}
