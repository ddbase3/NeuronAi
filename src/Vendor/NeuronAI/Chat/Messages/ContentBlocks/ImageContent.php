<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\ContentBlockType;
use NeuronAi\Vendor\NeuronAI\Chat\Enums\SourceType;
use function array_filter;
class ImageContent extends ContentBlock
{
    public function __construct(string $content, public readonly SourceType $sourceType, public readonly ?string $mediaType = null)
    {
        parent::__construct($content);
    }
    public function getType(): ContentBlockType
    {
        return ContentBlockType::IMAGE;
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(['type' => $this->getType(), 'content' => $this->content, 'source_type' => $this->sourceType, 'media_type' => $this->mediaType, 'meta' => $this->meta]);
    }
}
