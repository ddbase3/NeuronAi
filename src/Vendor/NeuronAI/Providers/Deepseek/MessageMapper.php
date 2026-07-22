<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Deepseek;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\MessageMapper as OpenAIMessageMapper;
class MessageMapper extends OpenAIMessageMapper
{
    protected function mapMessage(Message $message): array
    {
        $result = parent::mapMessage($message);
        if ($message->getMetadata('reasoning_content')) {
            $result['reasoning_content'] = $message->getMetadata('reasoning_content');
        }
        return $result;
    }
    protected function mapToolCall(ToolCallMessage $message): array
    {
        $result = parent::mapToolCall($message);
        if ($message->getMetadata('reasoning_content')) {
            $result['reasoning_content'] = $message->getMetadata('reasoning_content');
        }
        return $result;
    }
}
