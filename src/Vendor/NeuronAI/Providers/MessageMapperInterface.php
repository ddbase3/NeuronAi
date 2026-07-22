<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
interface MessageMapperInterface
{
    /**
     * @param array<Message> $messages
     */
    public function map(array $messages): array;
}
