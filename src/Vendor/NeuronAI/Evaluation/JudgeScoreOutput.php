<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Evaluation;

use NeuronAi\Vendor\NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAi\Vendor\NeuronAI\StructuredOutput\Validation\Rules\OutOfRange;
class JudgeScoreOutput
{
    public function __construct(
        #[SchemaProperty(description: 'Numeric score between 0.0 and 1.0', required: \true)]
        #[OutOfRange(min: 0.0, max: 1.0)]
        public float $score,
        #[SchemaProperty(description: 'Detailed reasoning for the given score', required: \true)]
        public string $reasoning
    )
    {
    }
}
