<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\ZAI\Audio;

use Generator;
use NeuronAi\Vendor\NeuronAI\Chat\Enums\SourceType;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
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
class ZAITranscription implements AIProviderInterface
{
    use HasHttpClient;
    /**
     * The main URL of the provider API.
     */
    protected string $baseUri = 'https://api.z.ai/api/paas/v4';
    /**
     * System instructions.
     */
    protected ?string $system = null;
    public function __construct(protected string $key, protected string $model, protected array $parameters = [], ?HttpClientInterface $httpClient = null)
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
     * @throws ProviderException
     */
    public function chat(Message ...$messages): Message
    {
        $message = end($messages);
        $body = ['model' => $this->model];
        $this->addFile($body, $message->getAudio());
        if ($message->getContent() !== null) {
            $body['prompt'] = $message->getContent();
        }
        $response = $this->httpClient->request(HttpRequest::post(uri: 'audio/transcriptions', body: $body))->json();
        $message = new AssistantMessage($response['text']);
        if (isset($response['usage'])) {
            $message->setUsage(new Usage($response['usage']['prompt_tokens'] ?? 0, $response['usage']['completion_tokens'] ?? 0));
        }
        return $message;
    }
    /**
     * @throws ProviderException
     * @throws HttpException
     */
    public function stream(Message ...$messages): Generator
    {
        $message = end($messages);
        $body = ['stream' => \true, 'model' => $this->model];
        $this->addFile($body, $message->getAudio());
        if ($message->getContent() !== null) {
            $body['prompt'] = $message->getContent();
        }
        $stream = $this->httpClient->stream(HttpRequest::post(uri: 'audio/transcriptions', body: $body));
        $content = '';
        $msgId = UniqueIdGenerator::generateId('msg_');
        $usage = new Usage(0, 0);
        while (!$stream->eof()) {
            if (!$line = SSEParser::parseNextSSEEvent($stream)) {
                continue;
            }
            if ($line['type'] === 'transcript.text.delta') {
                $content .= $line['delta'];
                yield new TextChunk($msgId, $line['delta']);
            }
            if ($line['type'] === 'transcript.text.done') {
                $usage->inputTokens = $line['usage']['prompt_tokens'] ?? 0;
                $usage->outputTokens = $line['usage']['completion_tokens'] ?? 0;
            }
        }
        $message = new AssistantMessage($content);
        $message->setUsage($usage);
        return $message;
    }
    protected function addFile(array &$body, AudioContent $audio): void
    {
        if ($audio->sourceType === SourceType::BASE64) {
            $body['file_base64'] = 'data:' . $audio->mediaType . ';base64,' . $audio->content;
        } elseif ($audio->sourceType === SourceType::URL) {
            $body['file'] = fopen($audio->content, 'r');
        } else {
            throw new ProviderException("Source type not supported: {$audio->sourceType->value}");
        }
    }
    public function structured(array|Message $messages, string $class, array $response_schema): Message
    {
        throw new ProviderException('Structured output is not supported for transcription.');
    }
    public function messageMapper(): MessageMapperInterface
    {
        throw new ProviderException('Messages are not supported for transcription.');
    }
    public function toolPayloadMapper(): ToolMapperInterface
    {
        throw new ProviderException('Tools are not supported for transcription.');
    }
    public function setTools(array $tools): AIProviderInterface
    {
        return $this;
    }
}
