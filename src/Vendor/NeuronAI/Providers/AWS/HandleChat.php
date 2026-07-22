<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\AWS;

use NeuronAi\Vendor\Aws\ResultInterface;
use NeuronAi\Vendor\GuzzleHttp\Promise\PromiseInterface;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Usage;
trait HandleChat
{
    public function chat(Message ...$messages): Message
    {
        return $this->chatAsync(...$messages)->wait();
    }
    public function chatAsync(Message ...$messages): PromiseInterface
    {
        $payload = $this->createPayLoad($messages);
        return $this->bedrockRuntimeClient->converseAsync($payload)->then(function (ResultInterface $result): ToolCallMessage|AssistantMessage {
            $usage = new Usage($result['usage']['inputTokens'] ?? 0, $result['usage']['outputTokens'] ?? 0, $result['usage']['cacheReadInputTokens'] ?? 0);
            $stopReason = $result['stopReason'] ?? '';
            if ($stopReason === 'tool_use') {
                $tools = [];
                foreach ($result['output']['message']['content'] ?? [] as $toolContent) {
                    if (isset($toolContent['toolUse'])) {
                        $tools[] = $this->createTool($toolContent);
                    }
                }
                $message = new ToolCallMessage(tools: $tools);
                $message->setUsage($usage);
                $message->setStopReason($stopReason);
                return $message;
            }
            $text = '';
            foreach ($result['output']['message']['content'] ?? [] as $content) {
                if (isset($content['text'])) {
                    $text .= $content['text'];
                }
            }
            $message = new AssistantMessage($text);
            $message->setUsage($usage);
            $message->setStopReason($stopReason);
            return $message;
        });
    }
}
