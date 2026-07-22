<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Cohere;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\MessageRole;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ReasoningContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\MessageMapper as OpenAIMessageMapper;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use function array_map;
use function json_encode;
class MessageMapper extends OpenAIMessageMapper
{
    protected function mapContentBlock(ContentBlockInterface $block): ?array
    {
        return match ($block::class) {
            FileContent::class => null,
            ReasoningContent::class => ['type' => 'thinking', 'thinking' => $block->content],
            default => parent::mapContentBlock($block),
        };
    }
    protected function mapToolCall(ToolCallMessage $message): array
    {
        return ['role' => MessageRole::ASSISTANT, 'tool_plan' => $message->getContent(), 'tool_calls' => array_map(fn(ToolInterface $tool): array => ['id' => $tool->getCallId(), 'type' => 'function', 'function' => ['name' => $tool->getName(), 'arguments' => $tool->getInputs() === [] ? '{}' : json_encode($tool->getInputs())]], $message->getTools())];
    }
}
