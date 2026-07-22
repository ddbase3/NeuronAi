<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Workflow\Exporter;

use NeuronAi\Vendor\NeuronAI\Workflow\NodeInterface;
interface ExporterInterface
{
    /**
     * @param array<string, NodeInterface> $eventNodeMap
     */
    public function export(array $eventNodeMap): string;
}
