<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\PostProcessor;

use NeuronAi\Vendor\NeuronAI\Chat\Messages\Message;
use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\RAG\Document;
use function array_map;
class JinaRerankerPostProcessor implements PostProcessorInterface
{
    use HasHttpClient;
    protected string $host = 'https://api.jina.ai/v1';
    public function __construct(protected string $key, protected string $model = 'jina-reranker-v2-base-multilingual', protected int $topN = 3, ?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri($this->host)->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->key]);
    }
    /**
     * @throws HttpException
     */
    public function process(Message $question, array $documents): array
    {
        $result = $this->httpClient->request(HttpRequest::post(uri: 'rerank', body: ['model' => $this->model, 'query' => $question->getContentBlocks(), 'top_n' => $this->topN, 'documents' => array_map(fn(Document $document): array => ['text' => $document->getContent()], $documents), 'return_documents' => \false]))->json();
        return array_map(function (array $item) use ($documents): Document {
            $document = $documents[$item['index']];
            $document->setScore($item['relevance_score']);
            return $document;
        }, $result['results']);
    }
}
