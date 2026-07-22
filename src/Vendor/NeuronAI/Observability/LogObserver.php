<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Observability;

use NeuronAi\Vendor\NeuronAI\Observability\Events\AgentError;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Deserialized;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Deserializing;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Extracted;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Extracting;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InferenceStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InferenceStop;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InstructionsChanged;
use NeuronAi\Vendor\NeuronAI\Observability\Events\InstructionsChanging;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MessageSaved;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MessageSaving;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MiddlewareEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MiddlewareStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\PostProcessed;
use NeuronAi\Vendor\NeuronAI\Observability\Events\PostProcessing;
use NeuronAi\Vendor\NeuronAI\Observability\Events\PreProcessed;
use NeuronAi\Vendor\NeuronAI\Observability\Events\PreProcessing;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Retrieved;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Retrieving;
use NeuronAi\Vendor\NeuronAI\Observability\Events\SchemaGenerated;
use NeuronAi\Vendor\NeuronAI\Observability\Events\SchemaGeneration;
use NeuronAi\Vendor\NeuronAI\Observability\Events\ToolCalled;
use NeuronAi\Vendor\NeuronAI\Observability\Events\ToolCalling;
use NeuronAi\Vendor\NeuronAI\Observability\Events\Validating;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowNodeEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowNodeStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowStart;
use NeuronAi\Vendor\NeuronAI\Workflow\NodeInterface;
use NeuronAi\Vendor\Psr\Log\LoggerInterface;
use NeuronAi\Vendor\Psr\Log\LogLevel;
use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_object;
/**
 * Credits: https://github.com/sixty-nine
 */
class LogObserver implements ObserverInterface
{
    public function __construct(protected readonly LoggerInterface $logger, protected string $level = LogLevel::INFO)
    {
    }
    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        $this->logger->log($this->level, $event, $this->serializeData($data));
    }
    /**
     * @return array<string, mixed>
     */
    protected function serializeData(mixed $data): array
    {
        if ($data === null) {
            return [];
        }
        if (is_array($data)) {
            return $data;
        }
        if (!is_object($data)) {
            return ['data' => $data];
        }
        return $this->serializeObject($data);
    }
    /**
     * Override this method in child classes to add or change serialization behaviour.
     */
    protected function serializeObject(object $data): array
    {
        return match ($data::class) {
            AgentError::class => $this->serializeAgentError($data),
            Deserializing::class, Deserialized::class => $this->serializeDeserializing($data),
            Extracted::class => $this->serializeExtracted($data),
            Extracting::class, InferenceStart::class, MessageSaving::class, MessageSaved::class => $this->serializeWithMessage($data),
            InferenceStop::class => $this->serializeInferenceStop($data),
            InstructionsChanging::class => $this->serializeInstructionsChanging($data),
            InstructionsChanged::class => $this->serializeInstructionsChanged($data),
            ToolCalling::class, ToolCalled::class => $this->serializeWithTool($data),
            Validating::class => $this->serializeValidating($data),
            Events\Validated::class => $this->serializeValidated($data),
            SchemaGeneration::class => $this->serializeSchemaGeneration($data),
            SchemaGenerated::class => $this->serializeSchemaGenerated($data),
            PreProcessing::class => $this->serializePreProcessing($data),
            PreProcessed::class => $this->serializePreProcessed($data),
            PostProcessing::class => $this->serializePostProcessing($data),
            PostProcessed::class => $this->serializePostProcessed($data),
            Retrieving::class => $this->serializeRetrieving($data),
            Retrieved::class => $this->serializeRetrieved($data),
            WorkflowNodeStart::class, WorkflowNodeEnd::class => $this->serializeWorkflowNode($data),
            MiddlewareStart::class => $this->serializeMiddlewareStart($data),
            MiddlewareEnd::class => $this->serializeMiddlewareEnd($data),
            WorkflowStart::class => $this->serializeWorkflowStart($data),
            WorkflowEnd::class => $this->serializeWorkflowEnd($data),
            default => [],
        };
    }
    /** @return array<string, mixed> */
    protected function serializeAgentError(AgentError $data): array
    {
        return ['error' => $data->exception->getMessage()];
    }
    /** @return array<string, mixed> */
    protected function serializeDeserializing(Deserializing|Deserialized $data): array
    {
        return ['class' => $data->class];
    }
    /** @return array<string, mixed> */
    protected function serializeExtracted(Extracted $data): array
    {
        return ['message' => $data->message->jsonSerialize(), 'schema' => $data->schema, 'json' => $data->json];
    }
    /** @return array<string, mixed> */
    protected function serializeWithMessage(Extracting|InferenceStart|MessageSaving|MessageSaved $data): array
    {
        return ['message' => $data->message->jsonSerialize()];
    }
    /** @return array<string, mixed> */
    protected function serializeInferenceStop(InferenceStop $data): array
    {
        return ['message' => $data->message->jsonSerialize(), 'response' => $data->response->jsonSerialize()];
    }
    /** @return array<string, mixed> */
    protected function serializeInstructionsChanging(InstructionsChanging $data): array
    {
        return ['instructions' => $data->instructions];
    }
    /** @return array<string, mixed> */
    protected function serializeInstructionsChanged(InstructionsChanged $data): array
    {
        return ['previous' => $data->previous, 'current' => $data->current];
    }
    /** @return array<string, mixed> */
    protected function serializeWithTool(ToolCalling|ToolCalled $data): array
    {
        return ['tool' => $data->tool->jsonSerialize()];
    }
    /** @return array<string, mixed> */
    protected function serializeValidating(Validating $data): array
    {
        return ['class' => $data->class, 'json' => $data->json];
    }
    /** @return array<string, mixed> */
    protected function serializeValidated(Events\Validated $data): array
    {
        return ['class' => $data->class, 'json' => $data->json, 'violations' => $data->violations];
    }
    /** @return array<string, mixed> */
    protected function serializeSchemaGeneration(SchemaGeneration $data): array
    {
        return ['class' => $data->class];
    }
    /** @return array<string, mixed> */
    protected function serializeSchemaGenerated(SchemaGenerated $data): array
    {
        return ['class' => $data->class, 'schema' => $data->schema];
    }
    /** @return array<string, mixed> */
    protected function serializePreProcessing(PreProcessing $data): array
    {
        return ['processor' => $data->processor, 'original' => $data->original->jsonSerialize()];
    }
    /** @return array<string, mixed> */
    protected function serializePreProcessed(PreProcessed $data): array
    {
        return ['processor' => $data->processor, 'processed' => $data->processed->jsonSerialize()];
    }
    /** @return array<string, mixed> */
    protected function serializePostProcessing(PostProcessing $data): array
    {
        return ['processor' => $data->processor, 'question' => $data->question->jsonSerialize(), 'documents' => $data->documents];
    }
    /** @return array<string, mixed> */
    protected function serializePostProcessed(PostProcessed $data): array
    {
        return ['processor' => $data->processor, 'question' => $data->question->jsonSerialize(), 'documents' => $data->documents];
    }
    /** @return array<string, mixed> */
    protected function serializeRetrieving(Retrieving $data): array
    {
        return ['question' => $data->question->jsonSerialize()];
    }
    /** @return array<string, mixed> */
    protected function serializeRetrieved(Retrieved $data): array
    {
        return ['question' => $data->question->jsonSerialize(), 'documents' => $data->documents];
    }
    /** @return array<string, mixed> */
    protected function serializeWorkflowNode(WorkflowNodeStart|WorkflowNodeEnd $data): array
    {
        return ['node' => $data->node];
    }
    /** @return array<string, mixed> */
    protected function serializeMiddlewareStart(MiddlewareStart $data): array
    {
        return ['class' => $data->middleware::class, 'node-event' => $data->event::class];
    }
    /** @return array<string, mixed> */
    protected function serializeMiddlewareEnd(MiddlewareEnd $data): array
    {
        return ['class' => $data->middleware::class];
    }
    /** @return list<array<string, class-string<\NeuronAI\Workflow\NodeInterface>>> */
    protected function serializeWorkflowStart(WorkflowStart $data): array
    {
        return array_map(fn(string $eventClass, NodeInterface $node): array => [$eventClass => $node::class], array_keys($data->eventNodeMap), array_values($data->eventNodeMap));
    }
    /** @return array<string, mixed> */
    protected function serializeWorkflowEnd(WorkflowEnd $data): array
    {
        return ['state' => $data->state->all()];
    }
}
