<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\Inspector\Neuron;

use NeuronAi\Vendor\Inspector\Exceptions\InspectorException;
use NeuronAi\Vendor\NeuronAI\Agent\Agent;
use NeuronAi\Vendor\NeuronAI\Observability\Events\BranchEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\BranchStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MiddlewareEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\MiddlewareStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowNodeEnd;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowNodeStart;
use NeuronAi\Vendor\NeuronAI\Observability\Events\WorkflowStart;
use NeuronAi\Vendor\NeuronAI\Workflow\NodeInterface;
use Exception;
use NeuronAi\Vendor\NeuronAI\Workflow\Workflow;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function str_contains;
trait HandleWorkflowEvents
{
    /**
     * @throws Exception
     */
    public function workflowStart(Workflow $workflow, string $event, WorkflowStart $data, ?string $branchId = null): void
    {
        if (!$this->inspector->isRecording()) {
            return;
        }
        $mapping = array_map(fn(string $eventClass, NodeInterface $node): array => [$eventClass => $node::class], array_keys($data->eventNodeMap), array_values($data->eventNodeMap));
        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($workflow::class)->setResult('success')->addContext('Mapping', $mapping);
        } elseif ($this->inspector->canAddSegments()) {
            $segment = $this->inspector->startSegment(self::SEGMENT_TYPE . '.workflow', $this->getBaseClassName($workflow::class))->setColor(self::STANDARD_COLOR);
            $segment->addContext('Mapping', $mapping);
            $this->segments[$workflow::class] = $segment;
        }
        $this->inspector->transaction()->setType('agent');
    }
    /**
     * @throws Exception
     */
    public function workflowEnd(Workflow $workflow, string $event, WorkflowEnd $data, ?string $branchId = null): void
    {
        if (array_key_exists($workflow::class, $this->segments)) {
            $this->segments[$workflow::class]->end()->addContext('State', $data->state->all());
            if ($workflow instanceof Agent) {
                foreach ($this->getAgentContext($workflow) as $key => $value) {
                    $this->segments[$workflow::class]->addContext($key, $value);
                }
            }
        } elseif ($this->inspector->canAddSegments()) {
            $transaction = $this->inspector->transaction();
            $transaction->addContext('State', $data->state->all());
            if ($workflow instanceof Agent) {
                foreach ($this->getAgentContext($workflow) as $key => $value) {
                    $transaction->addContext($key, $value);
                }
            }
            if ($this->autoFlush) {
                $this->inspector->flush();
            }
        }
    }
    /**
     * @throws InspectorException
     */
    public function branchStart(object $workflow, string $event, BranchStart $data, ?string $branchId = null): void
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }
        // Fork at the moment the branch starts, while the triggering node's segment
        // is still open in the parent scope — this gives branches correct nesting.
        $this->branchScopes[$data->branchId] = $this->inspector->fork();
    }
    public function branchEnd(object $workflow, string $event, BranchEnd $data, ?string $branchId = null): void
    {
        unset($this->branchScopes[$data->branchId]);
    }
    /**
     * @throws InspectorException
     */
    public function nodeStart(object $workflow, string $event, WorkflowNodeStart $data, ?string $branchId = null): void
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }
        $segment = $this->resolveScope($branchId)->startSegment(self::SEGMENT_TYPE . '.node', $this->getBaseClassName($data->node))->setColor(self::STANDARD_COLOR);
        $segment->addContext('State Before', $data->state->except('__steps'));
        $key = $branchId !== null ? "{$branchId}::{$data->node}" : $data->node;
        $this->segments[$key] = $segment;
    }
    public function nodeEnd(object $workflow, string $event, WorkflowNodeEnd $data, ?string $branchId = null): void
    {
        $key = $branchId !== null ? "{$branchId}::{$data->node}" : $data->node;
        if (array_key_exists($key, $this->segments)) {
            $segment = $this->segments[$key]->end();
            $segment->addContext('State After', $data->state->except('__steps'));
            unset($this->segments[$key]);
        }
    }
    public function middlewareStart(object $workflow, string $event, MiddlewareStart $data, ?string $branchId = null): void
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }
        $class = $data->middleware::class;
        $action = str_contains($event, 'before') ? 'before' : 'after';
        $key = $branchId !== null ? "{$branchId}::{$class}" : $class;
        $segment = $this->resolveScope($branchId)->startSegment(self::SEGMENT_TYPE . '.middleware', $this->getBaseClassName($class) . "::{$action}()")->setColor(self::STANDARD_COLOR);
        $segment->addContext('Event', $data->event);
        $this->segments[$key] = $segment;
    }
    public function middlewareEnd(object $workflow, string $event, MiddlewareEnd $data, ?string $branchId = null): void
    {
        $class = $data->middleware::class;
        $key = $branchId !== null ? "{$branchId}::{$class}" : $class;
        if (array_key_exists($key, $this->segments)) {
            $this->segments[$key]->end();
        }
    }
}
