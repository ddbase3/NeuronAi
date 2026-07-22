<?php

declare (strict_types=1);
namespace NeuronAi\Vendor\NeuronAI\RAG\VectorStore;

use NeuronAi\Vendor\NeuronAI\Exceptions\HttpException;
use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HasHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\RAG\Document;
use function array_map;
use function in_array;
use function trim;
use function array_chunk;
class PineconeVectorStore implements VectorStoreInterface
{
    use HasHttpClient;
    /**
     * Metadata filters.
     *
     * https://docs.pinecone.io/reference/api/2025-04/data-plane/query#body-filter
     */
    protected array $filters = [];
    public function __construct(
        string $key,
        protected string $indexUrl,
        protected int $topK = 4,
        string $version = '2025-04',
        protected string $namespace = '__default__',
        // Default namespace
        ?HttpClientInterface $httpClient = null
    )
    {
        $this->httpClient = ($httpClient ?? new GuzzleHttpClient())->withBaseUri(trim($this->indexUrl, '/') . '/')->withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json', 'Api-Key' => $key, 'X-Pinecone-API-Version' => $version]);
    }
    /**
     * @throws HttpException
     */
    public function addDocument(Document $document): VectorStoreInterface
    {
        return $this->addDocuments([$document]);
    }
    /**
     * @throws HttpException
     */
    public function addDocuments(array $documents): VectorStoreInterface
    {
        $chunks = array_chunk($documents, 100);
        foreach ($chunks as $chunk) {
            $this->httpClient->request(HttpRequest::post(uri: 'vectors/upsert', body: ['namespace' => $this->namespace, 'vectors' => array_map(fn(Document $document): array => ['id' => (string) $document->getId(), 'values' => $document->getEmbedding(), 'metadata' => ['content' => $document->getContent(), 'sourceType' => $document->getSourceType(), 'sourceName' => $document->getSourceName(), ...$document->metadata]], $chunk)]));
        }
        return $this;
    }
    /**
     * @deprecated Use deleteBy() instead.
     * @throws HttpException
     */
    public function deleteBySource(string $sourceType, string $sourceName): VectorStoreInterface
    {
        return $this->deleteBy($sourceType, $sourceName);
    }
    /**
     * @throws HttpException
     */
    public function deleteBy(string $sourceType, ?string $sourceName = null): VectorStoreInterface
    {
        $filter = $sourceName !== null ? ['sourceType' => ['$eq' => $sourceType], 'sourceName' => ['$eq' => $sourceName]] : ['sourceType' => ['$eq' => $sourceType]];
        $this->httpClient->request(HttpRequest::post(uri: 'vectors/delete', body: ['namespace' => $this->namespace, 'filter' => $filter]));
        return $this;
    }
    /**
     * @throws HttpException
     */
    public function similaritySearch(array $embedding): iterable
    {
        $queryParams = ['namespace' => $this->namespace, 'includeMetadata' => \true, 'includeValues' => \true, 'vector' => $embedding, 'topK' => $this->topK];
        // Only include filter parameter if filters are not empty
        if ($this->filters !== []) {
            $queryParams['filter'] = $this->filters;
        }
        $result = $this->httpClient->request(HttpRequest::post(uri: 'query', body: $queryParams))->json();
        return array_map(function (array $item): Document {
            $document = new Document();
            $document->id = $item['id'];
            $document->embedding = $item['values'];
            $document->content = $item['metadata']['content'];
            $document->sourceType = $item['metadata']['sourceType'];
            $document->sourceName = $item['metadata']['sourceName'];
            $document->score = $item['score'];
            foreach ($item['metadata'] as $name => $value) {
                if (!in_array($name, ['content', 'sourceType', 'sourceName'])) {
                    $document->addMetadata($name, $value);
                }
            }
            return $document;
        }, $result['matches']);
    }
    public function withFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }
}
