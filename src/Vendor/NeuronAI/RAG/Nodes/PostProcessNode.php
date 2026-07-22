<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Nodes;

use NeuronAi\Vendor\NeuronAI\Agent\AgentState;
use NeuronAi\Vendor\NeuronAI\Observability\Events\PostProcessed;
use NeuronAi\Vendor\NeuronAI\Observability\Events\PostProcessing;
use NeuronAi\Vendor\NeuronAI\RAG\Events\DocumentsProcessedEvent;
use NeuronAi\Vendor\NeuronAI\RAG\Events\DocumentsRetrievedEvent;
use NeuronAi\Vendor\NeuronAI\RAG\PostProcessor\PostProcessorInterface;
use NeuronAi\Vendor\NeuronAI\Workflow\Node;
/**
 * Applies post-processors to retrieved documents.
 *
 * Post-processors can rerank, filter, or transform documents (e.g., relevance scoring, diversity filtering).
 */
class PostProcessNode extends Node
{
    /**
     * @param PostProcessorInterface[] $postProcessors
     */
    public function __construct(private readonly array $postProcessors)
    {
    }
    /**
     * Apply post-processors sequentially to documents.
     */
    public function __invoke(DocumentsRetrievedEvent $event, AgentState $state): DocumentsProcessedEvent
    {
        $query = $event->query;
        $documents = $event->documents;
        foreach ($this->postProcessors as $processor) {
            $this->emit('rag-postprocessing', new PostProcessing($processor::class, $query, $documents));
            $documents = $processor->process($query, $documents);
            $this->emit('rag-postprocessed', new PostProcessed($processor::class, $query, $documents));
        }
        return new DocumentsProcessedEvent($query, $documents);
    }
}
