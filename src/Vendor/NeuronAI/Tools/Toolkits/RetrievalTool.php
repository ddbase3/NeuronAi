<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Tools\Toolkits;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;
use NeuronAi\Vendor\NeuronAI\RAG\Retrieval\RetrievalInterface;
use NeuronAi\Vendor\NeuronAI\Tools\PropertyType;
use NeuronAi\Vendor\NeuronAI\Tools\Tool;
use NeuronAi\Vendor\NeuronAI\Tools\ToolProperty;
class RetrievalTool extends Tool
{
    public function __construct(protected RetrievalInterface $retrieval)
    {
        parent::__construct(name: 'context_retrieval', description: 'Search for documents similar to a given query.');
    }
    protected function properties(): array
    {
        return [new ToolProperty(name: 'query', type: PropertyType::STRING, description: 'The query to retrieve documents for.', required: \true)];
    }
    public function __invoke(string $query): array
    {
        return $this->retrieval->retrieve(new UserMessage($query));
    }
}
