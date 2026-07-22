<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\AWS;

use NeuronAi\Vendor\Aws\BedrockRuntime\BedrockRuntimeClient;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\HandleWithTools;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use function count;
use function is_string;
use function json_decode;
class BedrockRuntime implements AIProviderInterface
{
    use HandleWithTools;
    use HandleChat;
    use HandleStream;
    use HandleStructured;
    protected ?string $system = null;
    protected MessageMapperInterface $messageMapper;
    protected ToolMapperInterface $toolPayloadMapper;
    public function __construct(protected BedrockRuntimeClient $bedrockRuntimeClient, protected string $model, protected array $inferenceConfig = [])
    {
    }
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }
    public function messageMapper(): MessageMapperInterface
    {
        return $this->messageMapper ?? $this->messageMapper = new MessageMapper();
    }
    public function toolPayloadMapper(): ToolMapperInterface
    {
        return $this->toolPayloadMapper ?? $this->toolPayloadMapper = new ToolMapper();
    }
    protected function createPayLoad(array $messages): array
    {
        $payload = ['modelId' => $this->model, 'messages' => $this->messageMapper()->map($messages), 'system' => [['text' => $this->system]]];
        if (count($this->inferenceConfig) > 0) {
            $payload['inferenceConfig'] = $this->inferenceConfig;
        }
        $tools = $this->toolPayloadMapper()->map($this->tools);
        if ($tools !== []) {
            $payload['toolConfig']['tools'] = $tools;
        }
        return $payload;
    }
    /**
     * @throws ProviderException
     */
    protected function createTool(array $toolContent): ToolInterface
    {
        $toolUse = $toolContent['toolUse'];
        $tool = $this->findTool($toolUse['name']);
        $tool->setCallId($toolUse['toolUseId']);
        if (is_string($toolUse['input'])) {
            $toolUse['input'] = json_decode($toolUse['input'], \true);
        }
        $tool->setInputs($toolUse['input'] ?? []);
        return $tool;
    }
    public function setHttpClient(HttpClientInterface $client): AIProviderInterface
    {
        // AWS BedrockRuntime uses its own BedrockRuntimeClient, not HTTP client
        // This method is a no-op to satisfy the AIProviderInterface
        return $this;
    }
}
