<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Ollama;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\MessageRole;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Usage;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use function array_unshift;
trait HandleChat
{
    /**
     * @throws ProviderException
     * @throws HttpException
     */
    public function chat(Message ...$messages): Message
    {
        // Include the system prompt
        if (isset($this->system)) {
            array_unshift($messages, new Message(MessageRole::SYSTEM, $this->system));
        }
        $body = ['stream' => \false, 'model' => $this->model, 'messages' => $this->messageMapper()->map($messages), ...$this->parameters];
        if (!empty($this->tools)) {
            $body['tools'] = $this->toolPayloadMapper()->map($this->tools);
        }
        $response = $this->httpClient->request(HttpRequest::post(uri: 'chat', body: $body));
        if (!$response->isSuccessful()) {
            throw new ProviderException("Ollama chat error: {$response->body}");
        }
        return $this->processResponse($response->json());
    }
    /**
     * @throws ProviderException
     */
    protected function processResponse(array $response): AssistantMessage
    {
        $message = $response['message'];
        if (isset($message['tool_calls'])) {
            $message = $this->createToolCallMessage($message['tool_calls'], $message['content'] ?? null);
        } else {
            $message = new AssistantMessage($message['content']);
        }
        if (isset($response['prompt_eval_count']) && isset($response['eval_count'])) {
            $message->setUsage(new Usage($response['prompt_eval_count'], $response['eval_count']));
        }
        if (isset($response['done_reason'])) {
            $message->setStopReason($response['done_reason']);
        }
        return $message;
    }
}
