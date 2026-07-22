<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\Supadata;

use NeuronAi\Vendor\NeuronAI\Tools\PropertyType;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use NeuronAi\Vendor\NeuronAI\Tools\ToolProperty;
use function json_decode;
/**
 * @method static static make(string $key)
 */
class SupadataYoutubeChannelTool extends Tool
{
    use HttpClient;
    public function __construct(protected string $key)
    {
        parent::__construct('get_youtube_channel_metadata', 'Retrieve metadata from a YouTube channel including name, description, subscriber count, and more.');
    }
    protected function properties(): array
    {
        return [new ToolProperty(name: 'channel', type: PropertyType::STRING, description: 'YouTube channel URL or ID', required: \true)];
    }
    public function __invoke(string $channel): array
    {
        $response = $this->getClient($this->key)->get('youtube/channel?id=' . $channel);
        return json_decode((string) $response->getBody(), \true);
    }
}
