<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Mistral;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\HandleWithTools;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\HandleStructured;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\ToolMapper;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use function array_map;
use function json_decode;
use function array_values;
class Mistral implements AIProviderInterface
{
    use HasHttpClient;
    use HandleWithTools;
    use HandleChat;
    use HandleStream;
    use HandleStructured;
    // From OpenAI
    protected string $baseUri = 'https://api.mistral.ai/v1';
    /**
     * System instructions.
     */
    protected ?string $system = null;
    protected MessageMapperInterface $messageMapper;
    protected ToolMapperInterface $toolPayloadMapper;
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(protected string $key, protected string $model, protected array $parameters = [], protected bool $strict_response = \false, ?HttpClientInterface $httpClient = null)
    {
        // Use the provided client or create default Guzzle client
        // Provider always configures authentication and base URI
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->baseUri)->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->key]);
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
     * @param array<int, array> $toolCalls
     * @param ContentBlockInterface|ContentBlockInterface[]|null $blocks
     *
     * @throws ProviderException
     */
    protected function createToolCallMessage(array $toolCalls, array|ContentBlockInterface|null $blocks = null): ToolCallMessage
    {
        $tools = array_map(fn(array $item): ToolInterface => $this->findTool($item['function']['name'])->setInputs(json_decode((string) $item['function']['arguments'], \true))->setCallId($item['id']), $toolCalls);
        $result = new ToolCallMessage($blocks, array_values($tools));
        $result->addMetadata('tool_calls', $toolCalls);
        return $result;
    }
}
