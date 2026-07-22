<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\ContentBlockType;
class TextContent extends ContentBlock
{
    public function getType(): ContentBlockType
    {
        return ContentBlockType::TEXT;
    }
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['type' => $this->getType(), 'content' => $this->content, 'meta' => $this->meta];
    }
}
