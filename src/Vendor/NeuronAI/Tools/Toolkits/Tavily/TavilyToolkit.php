<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits\Tavily;

use NeuronAi\Vendor\NeuronAI\Tools\Toolkits\AbstractToolkit;
/**
 * @method static static make(string $key)
 */
class TavilyToolkit extends AbstractToolkit
{
    public function __construct(protected string $key)
    {
    }
    public function guidelines(): ?string
    {
        return "- The Search API serves as your primary discovery mechanism for exploring topics and finding multiple sources.\n\n        - The Extract API functions as your precision instrument for retrieving complete content from known URLs\n        after you've identified specific pages of interest.\n- The Crawl API represents your comprehensive exploration\n        tool for systematically traversing websites to understand their structure and full content scope.\n\n\n        Effective search queries should be specific and targeted, typically using two to four keywords rather than\n        broad terms. For extraction tasks ensure you're working with valid URLs and remember this works best after\n        identifying pages through search. When utilizing crawl functionality, establish clear objectives\n        and appropriate scope boundaries for efficient website exploration.";
    }
    public function provide(): array
    {
        return [new TavilyExtractTool($this->key), new TavilySearchTool($this->key), new TavilyCrawlTool($this->key)];
    }
}
