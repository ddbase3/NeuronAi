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

namespace Base3\NeuronAi;

use Base3\AssistantFoundation\Api\IAgentContextProfileService;
use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\AssistantFoundation\Api\IAgentToolProfileService;
use Base3\AssistantFoundation\Api\IAiModelConfigurationProvider;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Accesscontrol\Api\IAccesscontrol;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Language\Api\ILanguage;
use Base3\CoreFoundation\Session\Api\ISession;
use Base3\NeuronAi\Api\INeuronAgentFactory;
use Base3\NeuronAi\Api\INeuronChatHistoryFactory;
use Base3\NeuronAi\Api\INeuronProviderFactory;
use Base3\NeuronAi\Service\NeuronAgentConfigFormService;
use Base3\NeuronAi\Service\NeuronConversationOwnerResolver;
use Base3\NeuronAi\Service\NeuronAgentExecutionService;
use Base3\NeuronAi\Service\NeuronAgentFactory;
use Base3\NeuronAi\Service\NeuronAgentToolFactory;
use Base3\NeuronAi\Service\NeuronChatHistoryFactory;
use Base3\NeuronAi\Service\NeuronChatHistoryRepository;
use Base3\NeuronAi\Service\NeuronContextInstructionsBuilder;
use Base3\NeuronAi\Service\NeuronConversationKeyFactory;
use Base3\NeuronAi\Service\NeuronExecutionEventMapper;
use Base3\NeuronAi\Service\NeuronProviderFactory;

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
				NeuronAgentToolFactory::class,
				fn() => new NeuronAgentToolFactory(),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				INeuronAgentFactory::class,
				fn($c) => new NeuronAgentFactory(
					$c->get(INeuronProviderFactory::class),
					$c->get(NeuronAgentToolFactory::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronConversationKeyFactory::class,
				fn() => new NeuronConversationKeyFactory(),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronConversationOwnerResolver::class,
				fn($c) => new NeuronConversationOwnerResolver(
					$c->get(IAccesscontrol::class),
					$c->get(ISession::class)
				),
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
					$c->get(NeuronConversationOwnerResolver::class),
					$c->get(NeuronChatHistoryRepository::class),
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
				NeuronContextInstructionsBuilder::class,
				fn() => new NeuronContextInstructionsBuilder(),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronAgentExecutionService::class,
				fn($c) => new NeuronAgentExecutionService(
					$c->get(INeuronAgentFactory::class),
					$c->get(INeuronChatHistoryFactory::class),
					$c->get(NeuronExecutionEventMapper::class),
					$c->get(IAgentContextProfileService::class),
					$c->get(IAgentToolProfileService::class),
					$c->get(NeuronContextInstructionsBuilder::class),
					$c->get(IAgentSuspensionRepository::class),
					$c->get(NeuronAgentToolFactory::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			)
			->set(
				NeuronAgentConfigFormService::class,
				fn($c) => new NeuronAgentConfigFormService(
					$c->get(IRequest::class),
					$c->get(ILanguage::class),
					$c->get(IAiModelConfigurationProvider::class),
					$c->get(IAgentContextProfileService::class),
					$c->get(IAgentToolProfileService::class)
				),
				IContainer::SHARED | IContainer::NOOVERWRITE
			);
	}
}
