<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of NeuronAi for BASE3 Framework.
 *
 * NeuronAi integrates the Neuron AI agent runtime with AssistantFoundation.
 * It ships an isolated, reproducible Neuron AI runtime for ILIAS and
 * standalone BASE3 installations.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/neuronai
 * https://github.com/ddbase3/NeuronAi
 **********************************************************************/

namespace NeuronAi\Http;

use NeuronAi\Vendor\NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpRequest;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpResponse;
use NeuronAi\Vendor\NeuronAI\HttpClient\StreamInterface;

/**
 * Sends Neuron chat-completions requests to the exact configured BASE3 endpoint.
 *
 * BASE3 connections store a complete request endpoint. Neuron providers normally
 * store an API base URI and append "chat/completions" themselves. This adapter
 * keeps the upstream provider implementation intact while replacing that one
 * relative request URI with the configured absolute endpoint.
 */
final class ConfiguredEndpointHttpClient implements HttpClientInterface {

	private HttpClientInterface $client;

	public function __construct(
		private readonly string $endpoint,
		?HttpClientInterface $client = null
	) {
		$this->assertEndpoint($endpoint);
		$this->client = $client ?? new GuzzleHttpClient();
	}

	public function request(HttpRequest $request): HttpResponse {
		return $this->client->request($this->mapRequest($request));
	}

	public function stream(HttpRequest $request): StreamInterface {
		return $this->client->stream($this->mapRequest($request));
	}

	/**
	 * Provider constructors call this method unconditionally. The configured
	 * endpoint is already complete, therefore the provider base URI is ignored.
	 */
	public function withBaseUri(string $baseUri): HttpClientInterface {
		return $this;
	}

	public function withHeaders(array $headers): HttpClientInterface {
		$this->client = $this->client->withHeaders($headers);
		return $this;
	}

	public function withTimeout(float $timeout): HttpClientInterface {
		$this->client = $this->client->withTimeout($timeout);
		return $this;
	}

	private function mapRequest(HttpRequest $request): HttpRequest {
		$uri = strtolower(trim($request->uri, " \t\n\r\0\x0B/"));
		if ($uri !== 'chat/completions') {
			throw new \RuntimeException(
				'Configured endpoint HTTP client only supports chat/completions requests, received: ' . $request->uri
			);
		}

		return new HttpRequest(
			$request->method,
			$this->endpoint,
			$request->headers,
			$request->body
		);
	}

	private function assertEndpoint(string $endpoint): void {
		$parts = parse_url(trim($endpoint));
		if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
			throw new \InvalidArgumentException('Configured LLM endpoint must be an absolute HTTP URL.');
		}
		if (!in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) {
			throw new \InvalidArgumentException('Configured LLM endpoint must use HTTP or HTTPS.');
		}
		if (isset($parts['fragment'])) {
			throw new \InvalidArgumentException('Configured LLM endpoint must not contain a fragment.');
		}
	}
}
