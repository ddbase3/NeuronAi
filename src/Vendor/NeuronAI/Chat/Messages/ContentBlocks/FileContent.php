<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\ContentBlockType;
use NeuronAi\Vendor\NeuronAI\Chat\Enums\SourceType;
use function array_filter;
class FileContent extends ContentBlock
{
    public function __construct(string $content, public readonly SourceType $sourceType, public readonly ?string $mediaType = null, public readonly ?string $filename = null)
    {
        parent::__construct($content);
    }
    public function getType(): ContentBlockType
    {
        return ContentBlockType::FILE;
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(['type' => $this->getType(), 'content' => $this->content, 'source_type' => $this->sourceType, 'media_type' => $this->mediaType, 'filename' => $this->filename, 'meta' => $this->meta]);
    }
}
