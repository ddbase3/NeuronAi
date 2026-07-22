<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks;

use NeuronAi\Vendor\NeuronAI\Chat\Enums\ContentBlockType;
use JsonSerializable;
interface ContentBlockInterface extends JsonSerializable
{
    public function accumulateContent(string $content): void;
    public function getContent(): string;
    public function getType(): ContentBlockType;
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
