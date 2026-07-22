<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\ZAI;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\SourceType;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\FileContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\VideoContent;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\MessageMapper as OpenAIMessageMapper;
class MessageMapper extends OpenAIMessageMapper
{
    protected function mapMessage(Message $message): array
    {
        $result = ['role' => $message->getRole(), 'content' => $this->mapBlocks($message->getContentBlocks())];
        if (($reasoning = $message->getReasoning()) instanceof \NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ReasoningContent) {
            $result['reasoning_content'] = $reasoning->content;
        }
        return $result;
    }
    protected function mapContentBlock(ContentBlockInterface $block): ?array
    {
        return match ($block::class) {
            TextContent::class => ['type' => 'text', 'text' => $block->content],
            ImageContent::class => $this->mapImageBlock($block),
            FileContent::class => $this->mapFileBlock($block),
            VideoContent::class => $this->mapVideoBlock($block),
            default => null,
        };
    }
    protected function mapFileBlock(FileContent $block): ?array
    {
        return match ($block->sourceType) {
            SourceType::BASE64, SourceType::ID => null,
            SourceType::URL => ['type' => 'file_url', 'file_url' => ['url' => $block->content]],
        };
    }
    protected function mapVideoBlock(VideoContent $block): ?array
    {
        return match ($block->sourceType) {
            SourceType::BASE64, SourceType::ID => null,
            SourceType::URL => ['type' => 'video_url', 'video_url' => ['url' => $block->content]],
        };
    }
}
