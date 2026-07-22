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

namespace NeuronAi\Service;

use AssistantFoundation\Api\IAiModelConfigurationProvider;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AiModelConfiguration;
use NeuronAi\Api\INeuronProviderFactory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Http\ConfiguredEndpointHttpClient;
use NeuronAi\Vendor\NeuronAI\HttpClient\HttpClientInterface;
use NeuronAi\Vendor\NeuronAI\Providers\AIProviderInterface;
use NeuronAi\Vendor\NeuronAI\Providers\Mistral\Mistral;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAi\Vendor\NeuronAI\Providers\OpenAILike;

final class NeuronProviderFactory implements INeuronProviderFactory {

	public function __construct(
		private readonly IAiModelConfigurationProvider $modelConfigurationProvider
	) {}

	public static function getName(): string {
		return 'neuronproviderfactory';
	}

	public function create(
		NeuronAgentConfiguration $configuration,
		AgentExecutionRequest $request
	): AIProviderInterface {
		$model = $this->modelConfigurationProvider->get($configuration->getLlmId());
		$httpClient = $this->createHttpClient($model);

		return match ($model->getDriver()) {
			'openai-chat' => $this->createOpenAi($model, $httpClient),
			'openai-compatible-chat' => new OpenAILike(
				$model->getEndpoint(),
				$model->getApiKey(),
				$model->getModel(),
				$model->getOptions(),
				false,
				$httpClient
			),
			'mistral-chat' => $this->createMistral($model, $httpClient),
			default => throw new \InvalidArgumentException(
				'Configured LLM driver is not supported by Neuron AI: ' . $model->getDriver()
			)
		};
	}

	private function createOpenAi(
		AiModelConfiguration $model,
		HttpClientInterface $httpClient
	): AIProviderInterface {
		$this->requireApiKey($model);

		return new OpenAI(
			$model->getApiKey(),
			$model->getModel(),
			$model->getOptions(),
			false,
			$httpClient
		);
	}

	private function createMistral(
		AiModelConfiguration $model,
		HttpClientInterface $httpClient
	): AIProviderInterface {
		$this->requireApiKey($model);

		return new Mistral(
			$model->getApiKey(),
			$model->getModel(),
			$model->getOptions(),
			false,
			$httpClient
		);
	}

	private function createHttpClient(AiModelConfiguration $model): HttpClientInterface {
		$endpoint = trim($model->getEndpoint());
		if ($endpoint === '') {
			throw new \InvalidArgumentException(
				'Configured LLM connection has no endpoint: ' . $model->getId()
			);
		}

		return new ConfiguredEndpointHttpClient($endpoint);
	}

	private function requireApiKey(AiModelConfiguration $model): void {
		if ($model->getApiKey() === '') {
			throw new \InvalidArgumentException(
				'Configured LLM connection has no resolved API key: ' . $model->getId()
			);
		}
	}
}
