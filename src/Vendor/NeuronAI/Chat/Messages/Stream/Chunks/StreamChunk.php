<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages\Stream\Chunks;

abstract class StreamChunk
{
    public function __construct(public readonly ?string $messageId = null)
    {
    }
    /**
     * Convert the chunk to an array representation.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
