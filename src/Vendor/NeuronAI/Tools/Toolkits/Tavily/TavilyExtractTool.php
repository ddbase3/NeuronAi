<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\Tavily;

use NeuronAi\Vendor\GuzzleHttp\Client;
use NeuronAi\Vendor\GuzzleHttp\RequestOptions;
use NeuronAi\Vendor\NeuronAI\Exceptions\ToolException;
use NeuronAi\Vendor\NeuronAI\Tools\PropertyType;
use NeuronAi\Vendor\NeuronAI\Tools\ToolProperty;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use function array_merge;
use function filter_var;
use function json_decode;
use function trim;
use const FILTER_VALIDATE_URL;
/**
 * @method static static make(string $key)
 */
class TavilyExtractTool extends Tool
{
    protected Client $client;
    protected string $url = 'https://api.tavily.com/';
    protected array $options = [];
    /**
     * @param string $key Tavily API key.
     */
    public function __construct(protected string $key)
    {
        parent::__construct('url_reader', 'Get the content of a URL in markdown format.');
    }
    protected function properties(): array
    {
        return [new ToolProperty('url', PropertyType::STRING, 'The URL to read.', \true)];
    }
    public function __invoke(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ToolException('Invalid URL.');
        }
        $result = $this->getClient()->post('extract', [RequestOptions::JSON => array_merge($this->options, ['urls' => [$url]])]);
        $result = json_decode((string) $result->getBody(), \true);
        return $result['results'][0];
    }
    protected function getClient(): Client
    {
        return $this->client ?? $this->client = new Client(['base_uri' => trim($this->url, '/') . '/', 'headers' => ['Authorization' => 'Bearer ' . $this->key, 'Content-Type' => 'application/json', 'Accept' => 'application/json']]);
    }
    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }
}
