<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Embeddings;

use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
class GeminiEmbeddingsProvider extends AbstractEmbeddingsProvider
{
    use HasHttpClient;
    protected string $baseUri = 'https://generativelanguage.googleapis.com/v1beta/models/';
    public function __construct(protected string $key, protected string $model, protected array $config = [], ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->baseUri)->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'x-goog-api-key' => $this->key]);
    }
    /**
     * @throws HttpException
     */
    public function embedText(string $text): array
    {
        $response = $this->httpClient->request(HttpRequest::post(uri: "{$this->model}:embedContent", body: ['content' => ['parts' => [['text' => $text]]], ...$this->config]))->json();
        return $response['embedding']['values'];
    }
}
