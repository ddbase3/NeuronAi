<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Workflow\Executor;

use Generator;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\Event;
use NeuronAi\Vendor\NeuronAI\Workflow\Events\ParallelEvent;
use NeuronAi\Vendor\NeuronAI\Workflow\Interrupt\BranchInterrupt;
use NeuronAi\Vendor\NeuronAI\Workflow\Interrupt\InterruptRequest;
use NeuronAi\Vendor\NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAi\Vendor\NeuronAI\Workflow\Workflow;
use NeuronAi\Vendor\NeuronAI\Workflow\WorkflowInterface;
use function NeuronAi\Vendor\Amp\async;
/**
 * Executor that runs parallel branches concurrently using Amp fibers.
 *
 * Drop-in replacement for WorkflowExecutor: regular nodes execute sequentially
 * as usual; branches from any node returning ParallelEvent execute as concurrent
 * Amp futures.
 *
 * Usage:
 *   Workflow::make()->setExecutor(new AsyncExecutor())
 */
class AsyncExecutor extends WorkflowExecutor
{
    /**
     * Override to run branches as concurrent Amp futures instead of sequentially.
     *
     * @return Generator<int, Event, mixed, Event>
     * @throws WorkflowInterrupt
     */
    protected function executeParallelBranches(WorkflowInterface $workflow, ParallelEvent $parallelEvent, ?WorkflowInterrupt $interrupt = null, ?InterruptRequest $resumeRequest = null): Generator
    {
        $futures = [];
        foreach ($parallelEvent->branches as $branchId => $branchEvent) {
            if ($parallelEvent->hasResult($branchId)) {
                continue;
            }
            // When $interrupt is non-null and its branch matches, $isResuming is true
            // and $interrupt is guaranteed non-null for the rest of this iteration.
            $isResuming = $branchId === $interrupt?->getBranchId();
            $futures[$branchId] = async(fn(): BranchResult => $this->executeBranch($workflow, $branchId, $isResuming ? $interrupt->getEvent() : $branchEvent, $isResuming ? $resumeRequest : null, $isResuming ? $interrupt->getNode() : null));
        }
        $firstBranchInterrupt = null;
        foreach ($futures as $branchId => $future) {
            try {
                $result = $future->await();
                $parallelEvent->setResult($branchId, $result->result);
                foreach ($result->streamedEvents as $streamedEvent) {
                    yield $streamedEvent;
                }
            } catch (BranchInterrupt $branchInterrupt) {
                if (!$firstBranchInterrupt instanceof BranchInterrupt) {
                    $firstBranchInterrupt = $branchInterrupt;
                }
            }
        }
        if ($firstBranchInterrupt instanceof BranchInterrupt) {
            throw new WorkflowInterrupt(request: $firstBranchInterrupt->original->getRequest(), node: $firstBranchInterrupt->original->getNode(), state: $workflow->resolveState(), event: $firstBranchInterrupt->original->getEvent(), branchId: $firstBranchInterrupt->branchId, parallelEvent: $parallelEvent, completedBranchResults: $parallelEvent->getAllResults());
        }
        return $parallelEvent;
    }
}
