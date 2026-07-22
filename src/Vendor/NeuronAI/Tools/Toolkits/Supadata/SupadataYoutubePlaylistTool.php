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
class SupadataYoutubePlaylistTool extends Tool
{
    use HttpClient;
    public function __construct(protected string $key)
    {
        parent::__construct('get_youtube_playlist_metadata', 'Retrieve metadata from a YouTube playlist including title, description, video count, and more.');
    }
    protected function properties(): array
    {
        return [new ToolProperty(name: 'playlist', type: PropertyType::STRING, description: 'YouTube playlist URL or ID', required: \true)];
    }
    public function __invoke(string $playlist): array
    {
        $response = $this->getClient($this->key)->get('youtube/playlist?id=' . $playlist);
        return json_decode((string) $response->getBody(), \true);
    }
}
