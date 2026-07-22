<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\ElevenLabs;

use Generator;
use NeuronAi\Vendor\NeuronAI\Chat\Enums\SourceType;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\AudioChunk;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\UniqueIdGenerator;
use function base64_encode;
use function end;
class ElevenLabsTextToSpeech implements AIProviderInterface
{
    use HasHttpClient;
    protected string $baseUri = 'https://api.elevenlabs.io/v1/text-to-speech';
    /**
     * System instructions.
     */
    protected ?string $system = null;
    public function __construct(protected string $key, protected string $model, protected string $voiceId, protected array $parameters = [], ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->baseUri)->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'xi-api-key' => $this->key]);
    }
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }
    /**
     * @throws HttpException
     */
    public function chat(Message ...$messages): Message
    {
        $message = end($messages);
        $body = ['model_id' => $this->model, 'text' => $message->getContent()];
        $response = $this->httpClient->request(HttpRequest::post(uri: $this->voiceId, body: $body));
        return new AssistantMessage(new AudioContent(base64_encode($response->body), SourceType::BASE64));
    }
    /**
     * @throws HttpException
     */
    public function stream(Message ...$messages): Generator
    {
        $message = end($messages);
        $json = ['model_id' => $this->model, 'text' => $message->getContent()];
        $response = $this->httpClient->stream(HttpRequest::post(uri: $this->voiceId, body: $json));
        $audio = '';
        $msgId = UniqueIdGenerator::generateId('msg_');
        while (!$response->eof()) {
            $chunk = $response->read(1024);
            yield new AudioChunk($msgId, $chunk);
            $audio .= $chunk;
        }
        return new AssistantMessage(new AudioContent(base64_encode($audio), SourceType::BASE64));
    }
    public function structured(array|Message $messages, string $class, array $response_schema): Message
    {
        throw new ProviderException('Structured output is not supported by OpenAI Text to Speech.');
    }
    public function messageMapper(): MessageMapperInterface
    {
        throw new ProviderException('Messages are not supported by OpenAI Text to Speech.');
    }
    public function toolPayloadMapper(): ToolMapperInterface
    {
        throw new ProviderException('Tools are not supported by OpenAI Text to Speech.');
    }
    public function setTools(array $tools): AIProviderInterface
    {
        return $this;
    }
}
