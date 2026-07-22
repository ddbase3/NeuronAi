<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\OpenAI\Responses;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Usage;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use function array_filter;
use function array_key_exists;
use function json_encode;
use function is_array;
/**
 * Inspired by Andrew Monty - https://github.com/AndrewMonty
 */
trait HandleChat
{
    /**
     * @throws ProviderException
     * @throws HttpException
     */
    public function chat(Message ...$messages): Message
    {
        $body = ['model' => $this->model, 'input' => $this->messageMapper()->map($messages), ...$this->parameters];
        // Attach the system prompt
        if (isset($this->system)) {
            $body['instructions'] = $this->system;
        }
        // Attach tools
        if (!empty($this->tools)) {
            $body['tools'] = $this->toolPayloadMapper()->map($this->tools);
        }
        $response = $this->httpClient->request(HttpRequest::post(uri: 'responses', body: $body));
        return $this->processChatResult($response->json());
    }
    /**
     * @throws ProviderException
     */
    protected function processChatResult(array $result): AssistantMessage
    {
        if (isset($result['error'])) {
            throw new ProviderException("OpenAI API Error: " . ($result['error']['message'] ?? json_encode($result['error'])));
        }
        if (!array_key_exists('output', $result) || !is_array($result['output'])) {
            throw new ProviderException("OpenAI API Error: No output - " . json_encode($result));
        }
        $toolCalls = array_filter($result['output'], fn(array $item): bool => $item['type'] == 'function_call');
        $usage = new Usage($result['usage']['input_tokens'] ?? 0, $result['usage']['output_tokens'] ?? 0, $result['usage']['input_tokens_details']['cached_tokens'] ?? 0, $result['usage']['output_tokens_details']['reasoning_tokens'] ?? 0);
        if ($toolCalls !== []) {
            $message = $this->createToolCallMessage($toolCalls)->setUsage($usage);
        } else {
            $message = $this->createAssistantMessage($result)->setUsage($usage);
        }
        if (isset($result['status'])) {
            $message->setStopReason($result['status']);
        }
        return $message;
    }
}
