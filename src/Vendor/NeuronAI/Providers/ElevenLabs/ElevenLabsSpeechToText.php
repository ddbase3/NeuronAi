<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\ElevenLabs;

use Generator;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use function end;
use function fopen;
class ElevenLabsSpeechToText implements AIProviderInterface
{
    use HasHttpClient;
    protected string $baseUri = 'https://api.elevenlabs.io/v1/speech-to-text';
    /**
     * System instructions.
     */
    protected ?string $system = null;
    public function __construct(protected string $key, protected string $model, protected array $parameters = [], ?HttpClientInterface $httpClient = null)
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
        $body = ['file' => fopen($message->getAudio()->getContent(), 'r'), 'model' => $this->model];
        $response = $this->httpClient->request(HttpRequest::post(uri: 'audio/transcriptions', body: $body))->json();
        return new AssistantMessage($response['text']);
    }
    /**
     * @throws ProviderException
     */
    public function stream(Message ...$messages): Generator
    {
        throw new ProviderException('Streaming is not supported by OpenAI Text to Speech.');
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
