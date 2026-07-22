<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Providers\Anthropic;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\Exceptions\ProviderException;
use NeuronAi\Vendor\NeuronAI\HandleContent;
use function json_encode;
use function is_array;
use const PHP_EOL;
trait HandleStructured
{
    use HandleContent;
    /**
     * @throws ProviderException
     * @throws HttpException
     */
    public function structured(array|Message $messages, string $class, array $response_format): Message
    {
        $originalSystem = $this->system;
        try {
            $this->system .= PHP_EOL . "<output-constraint>" . PHP_EOL . "Your response must be a JSON string following this schema: " . PHP_EOL . json_encode($response_format) . PHP_EOL . "</output-constraint>";
            return $this->chat(...is_array($messages) ? $messages : [$messages]);
        } finally {
            $this->system = $originalSystem;
        }
    }
}
