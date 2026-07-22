<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability\Events;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
class MessageSaving
{
    public function __construct(public Message $message)
    {
    }
}
