<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\OpenAI\Audio;

use Generator;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Usage;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Providers\SSEParser;
use NeuronAi\Vendor\NeuronAI\Providers\ToolMapperInterface;
use NeuronAi\Vendor\NeuronAI\UniqueIdGenerator;
use function end;
use function fopen;
class OpenAISpeechToText implements AIProviderInterface
{
    use HasHttpClient;
    /**
     * The main URL of the provider API.
     */
    protected string $baseUri = 'https://api.openai.com/v1';
    /**
     * System instructions.
     */
    protected ?string $system = null;
    public function __construct(protected string $key, protected string $model, protected string $language = 'en', protected array $parameters = [], ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->baseUri)->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $this->key]);
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
        $body = ['file' => fopen($message->getAudio()->getContent(), 'r'), 'model' => $this->model, 'language' => $this->language, 'response_format' => 'json'];
        if ($message->getContent() !== null) {
            $body['prompt'] = $message->getContent();
        }
        $response = $this->httpClient->request(HttpRequest::post(uri: 'audio/transcriptions', body: $body))->json();
        $message = new AssistantMessage($response['text']);
        $message->setUsage(new Usage($response['usage']['input_tokens'], $response['usage']['output_tokens']));
        return $message;
    }
    /**
     * @throws HttpException
     * @throws ProviderException
     */
    public function stream(Message ...$messages): Generator
    {
        $message = end($messages);
        $body = ['stream' => \true, 'file' => fopen($message->getAudio()->getContent(), 'r'), 'model' => $this->model, 'language' => $this->language, 'response_format' => 'json'];
        if ($message->getContent() !== null) {
            $body['prompt'] = $message->getContent();
        }
        $stream = $this->httpClient->stream(HttpRequest::post(uri: 'audio/transcriptions', body: $body));
        $content = '';
        $usage = new Usage(0, 0);
        $msgId = UniqueIdGenerator::generateId('msg_');
        while (!$stream->eof()) {
            if (!$line = SSEParser::parseNextSSEEvent($stream)) {
                continue;
            }
            if ($line['type'] === 'transcript.text.delta') {
                $content .= $line['delta'];
                yield new TextChunk($msgId, $line['delta']);
            }
            if ($line['type'] === 'transcript.text.done') {
                $usage->inputTokens = $line['usage']['input_tokens'] ?? 0;
                $usage->outputTokens = $line['usage']['output_tokens'] ?? 0;
            }
        }
        $message = new AssistantMessage($content);
        $message->setUsage($usage);
        return $message;
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
