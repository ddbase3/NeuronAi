<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Evaluation\Contracts;

use NeuronAi\Vendor\NeuronAI\Evaluation\Runner\EvaluatorSummary;
interface EvaluationOutputInterface
{
    public function output(EvaluatorSummary $summary): void;
}
