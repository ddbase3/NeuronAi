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

namespace NeuronAi;

use AssistantFoundation\Api\IAiModelConfigurationProvider;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\State\Api\IStateStore;
use NeuronAi\Api\INeuronAgentFactory;
use NeuronAi\Api\INeuronChatHistoryFactory;
use NeuronAi\Api\INeuronProviderFactory;
use NeuronAi\Service\NeuronAgentConfigFormService;
use NeuronAi\Service\NeuronAgentExecutionService;
use NeuronAi\Service\NeuronAgentFactory;
use NeuronAi\Service\NeuronChatHistoryFactory;
use NeuronAi\Service\NeuronChatHistoryRepository;
use NeuronAi\Service\NeuronConversationKeyFactory;
use NeuronAi\Service\NeuronExecutionEventMapper;
use NeuronAi\Service\NeuronProviderFactory;

class NeuronAiPlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	public static function getName(): string {
		return 'neuronaiplugin';
	}

	public function init() {
		VendorBootstrap::init();

		$this->container
			->set(self::getName(), $this, IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(
				INeuronProviderFactory::class,
				fn($c) => new NeuronProviderFactory($c->get(IAiModelConfigurationProvider::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				INeuronAgentFactory::class,
				fn($c) => new NeuronAgentFactory($c->get(INeuronProviderFactory::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronConversationKeyFactory::class,
				fn() => new NeuronConversationKeyFactory(),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronChatHistoryRepository::class,
				fn($c) => new NeuronChatHistoryRepository($c->get(IDatabase::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				INeuronChatHistoryFactory::class,
				fn($c) => new NeuronChatHistoryFactory(
					$c->get(NeuronConversationKeyFactory::class),
					$c->get(NeuronChatHistoryRepository::class),
					$c->get(IStateStore::class),
					$c->get(IAiModelConfigurationProvider::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronExecutionEventMapper::class,
				fn() => new NeuronExecutionEventMapper(),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronAgentExecutionService::class,
				fn($c) => new NeuronAgentExecutionService(
					$c->get(INeuronAgentFactory::class),
					$c->get(INeuronChatHistoryFactory::class),
					$c->get(NeuronExecutionEventMapper::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronAgentConfigFormService::class,
				fn($c) => new NeuronAgentConfigFormService(
					$c->get(IRequest::class),
					$c->get(IAiModelConfigurationProvider::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			);
	}
}
