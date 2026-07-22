<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\AWS;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAi\Vendor\NeuronAI\Providers\BasicStreamState;
class StreamState extends BasicStreamState
{
    public function updateContentBlock(int $index, ContentBlockInterface $block): void
    {
        if (!isset($this->blocks[$index])) {
            $this->blocks[$index] = $block;
        } else {
            $this->blocks[$index]->accumulateContent($block->getContent());
        }
    }
}
