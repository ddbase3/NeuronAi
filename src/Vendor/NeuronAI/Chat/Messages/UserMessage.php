<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Chat\Messages;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAi\Vendor\NeuronAI\Chat\Enums\MessageRole;
/**
 * @method static static make(string|ContentBlockInterface|array<int, ContentBlockInterface>|null $content = null)
 */
class UserMessage extends Message
{
    /**
     * @param string|ContentBlockInterface|ContentBlockInterface[]|null $content
     */
    public function __construct(string|ContentBlockInterface|array|null $content)
    {
        parent::__construct(MessageRole::USER, $content);
    }
}
