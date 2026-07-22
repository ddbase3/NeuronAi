<?php

namespace NeuronAi\Vendor\GuzzleHttp;

use NeuronAi\Vendor\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
