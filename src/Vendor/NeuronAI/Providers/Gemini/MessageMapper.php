<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Gemini;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\MessageRole;
use NeuronAi\Vendor\NeuronAI\Chat\Enums\SourceType;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\AudioContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ContentBlock;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ReasoningContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\Providers\MessageMapperInterface;
use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
use stdClass;
use function array_filter;
use function array_map;
use function array_values;
class MessageMapper implements MessageMapperInterface
{
    /**
     * @throws ProviderException
     */
    public function map(array $messages): array
    {
        $mapping = [];
        foreach ($messages as $message) {
            $mapping[] = match ($message::class) {
                Message::class, UserMessage::class, AssistantMessage::class => $this->mapMessage($message),
                ToolCallMessage::class => $this->mapToolCall($message),
                ToolResultMessage::class => $this->mapToolsResult($message),
                default => throw new ProviderException('Could not map message type ' . $message::class),
            };
        }
        return $mapping;
    }
    protected function mapMessage(Message $message): array
    {
        return ['role' => $message->getRole() === MessageRole::ASSISTANT->value ? MessageRole::MODEL : $message->getRole(), 'parts' => $this->mapBlocks($message->getContentBlocks())];
    }
    protected function mapBlocks(array $blocks): array
    {
        return array_values(array_filter(array_map($this->mapContentBlock(...), $blocks)));
    }
    protected function mapContentBlock(ContentBlock $block): ?array
    {
        $item = match ($block::class) {
            TextContent::class => ['text' => $block->content],
            ReasoningContent::class => ['thought' => \true, 'text' => $block->content],
            ImageContent::class, FileContent::class, AudioContent::class, VideoContent::class => $this->mapMediaBlock($block),
            default => null,
        };
        if ($signature = $block->getMetadata('thought_signature')) {
            $item['thought_signature'] = $signature;
        }
        return $item;
    }
    protected function mapMediaBlock(ImageContent|FileContent|AudioContent|VideoContent $block): ?array
    {
        return match ($block->sourceType) {
            SourceType::URL => ['file_data' => ['file_uri' => $block->content, 'mime_type' => $block->mediaType]],
            SourceType::BASE64 => ['inline_data' => ['data' => $block->content, 'mime_type' => $block->mediaType]],
            default => null,
        };
    }
    protected function mapToolCall(ToolCallMessage $message): array
    {
        $parts = [];
        if ($contentBlocks = $message->getContentBlocks()) {
            $parts = $this->mapBlocks($contentBlocks);
        }
        foreach ($message->getTools() as $index => $tool) {
            $part = ['functionCall' => ['name' => $tool->getName(), 'args' => $tool->getInputs() !== [] ? $tool->getInputs() : new stdClass()]];
            if ($index === 0 && $signature = $message->getMetadata('thought_signature')) {
                $part['thought_signature'] = $signature;
            }
            $parts[] = $part;
        }
        return ['role' => MessageRole::MODEL, 'parts' => $parts];
    }
    protected function mapToolsResult(ToolResultMessage $message): array
    {
        $parts = array_map(fn(ToolInterface $tool): array => ['functionResponse' => ['name' => $tool->getName(), 'response' => ['name' => $tool->getName(), 'content' => $tool->getResult()]]], $message->getTools());
        if ($contentBlocks = $message->getContentBlocks()) {
            $parts = [...$parts, ...$this->mapBlocks($contentBlocks)];
        }
        return ['role' => MessageRole::USER, 'parts' => $parts];
    }
}
