<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\Exceptions;

use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpResponse;
use Throwable;
/**
 * Exception thrown when HTTP request fails.
 */
class HttpException extends NeuronException
{
    public function __construct(string $message, public readonly ?HttpRequest $request = null, public readonly ?HttpResponse $response = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
