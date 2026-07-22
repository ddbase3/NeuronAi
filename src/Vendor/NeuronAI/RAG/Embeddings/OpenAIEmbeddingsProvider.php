<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\Embeddings;

use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\RAG\Document;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use function array_chunk;
use function array_map;
use function array_merge;
class OpenAIEmbeddingsProvider extends AbstractEmbeddingsProvider
{
    use HasHttpClient;
    protected string $baseUri = 'https://api.openai.com/v1';
    public function __construct(protected string $key, protected string $model, protected ?int $dimensions = 1024, ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->baseUri)->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->key]);
    }
    /**
     * @throws HttpException
     */
    public function embedDocuments(array $documents): array
    {
        $chunks = array_chunk($documents, 100);
        foreach ($chunks as $chunk) {
            $response = $this->httpClient->request(HttpRequest::post('embeddings', ['model' => $this->model, 'input' => array_map(fn(Document $document): string => $document->getContent(), $chunk), 'encoding_format' => 'float', ...$this->dimensions ? ['dimensions' => $this->dimensions] : []]))->json();
            foreach ($response['data'] as $index => $item) {
                $chunk[$index]->embedding = $item['embedding'];
            }
        }
        return array_merge(...$chunks);
    }
    /**
     * @throws HttpException
     */
    public function embedText(string $text): array
    {
        $response = $this->httpClient->request(HttpRequest::post('embeddings', ['model' => $this->model, 'input' => $text, 'encoding_format' => 'float', ...$this->dimensions ? ['dimensions' => $this->dimensions] : []]))->json();
        return $response['data'][0]['embedding'];
    }
    public function withoutDimensions(): self
    {
        $this->dimensions = null;
        return $this;
    }
}
