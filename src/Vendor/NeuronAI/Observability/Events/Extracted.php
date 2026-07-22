<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
class Extracted
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(public Message $message, public array $schema, public ?string $json)
    {
    }
}
