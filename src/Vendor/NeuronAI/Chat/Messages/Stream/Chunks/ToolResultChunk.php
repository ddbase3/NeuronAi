<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks;

use NeuronAi\Vendor\NeuronAI\Tools\ToolInterface;
class ToolResultChunk extends StreamChunk
{
    public function __construct(public readonly ToolInterface $tool)
    {
        parent::__construct();
    }
    public function toArray(): array
    {
        return ['messageId' => $this->messageId, 'tools' => $this->tool->jsonSerialize()];
    }
}
