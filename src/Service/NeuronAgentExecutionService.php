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

use AssistantFoundation\Api\IAgentConfirmableToolSet;
use AssistantFoundation\Api\IAgentContextProfileService;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentRuntimeService;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Api\IAgentToolProfileService;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentExecutionEvent;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResult;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentState;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Dto\AgentSuspensionState;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use NeuronAi\Api\INeuronAgentFactory;
use NeuronAi\Api\INeuronChatHistoryFactory;
use NeuronAi\Dto\NeuronAgentConfiguration;
use NeuronAi\Dto\NeuronChatHistoryLease;
use NeuronAi\Exception\NeuronToolSuspensionException;
use NeuronAi\Tool\NeuronAgentTool;
use NeuronAi\Vendor\NeuronAI\Agent\AgentHandler;
use NeuronAi\Vendor\NeuronAI\Agent\AgentInterface;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAi\Vendor\NeuronAI\Chat\Messages\UserMessage;

/**
 * Neuron AI implementation of the transport-neutral agent execution service.
 */
final class NeuronAgentExecutionService implements IAgentRuntimeService {

	private const ASSISTANT_OUTPUT_ID = 'assistant';
	private const SUSPENSION_TTL_SECONDS = 900;
	private const CONTINUATION_STATE_KEY = 'neuron_continuation';

	public function __construct(
		private readonly INeuronAgentFactory $agentFactory,
		private readonly INeuronChatHistoryFactory $chatHistoryFactory,
		private readonly NeuronExecutionEventMapper $eventMapper,
		private readonly IAgentContextProfileService $contextProfileService,
		private readonly IAgentToolProfileService $toolProfileService,
		private readonly NeuronContextInstructionsBuilder $contextInstructionsBuilder,
		private readonly IAgentSuspensionRepository $suspensionRepository,
		private readonly NeuronAgentToolFactory $toolFactory
	) {}

	public static function getName(): string {
		return 'neuronagentexecutionservice';
	}

	public static function getRuntimeId(): string {
		return 'neuronai';
	}

	public static function getRuntimeLabel(): string {
		return 'Neuron AI';
	}

	public static function getRuntimeDescription(): string {
		return 'Executes Neuron AI agents with provider streaming and optional MCP tools.';
	}

	public static function getDefaultPriority(): int {
		return 0;
	}

	public function execute(
		AgentExecutionRequest $request,
		?IAgentEventSink $eventSink = null
	): AgentExecutionResult {
		$inputs = $request->getInputs();
		$resume = $this->readResume($inputs);
		$prompt = $this->readString($inputs, 'prompt');
		if ($prompt === '' && $resume === null) {
			throw new \InvalidArgumentException('Neuron AI prompt is required.');
		}

		$executionText = $prompt !== ''
			? $prompt
			: trim((string)($resume?->getResponseText() ?? ''));
		$messageId = uniqid('msg_', true);
		$this->emit($eventSink, 'msgid', ['id' => $messageId]);
		$executionRequest = $this->withExecutionContext($request, $messageId, $executionText);
		$eventMapper = clone $this->eventMapper;
		$eventMapper->beginExecution();

		$historyLease = null;
		$claim = null;
		$claimConsumed = false;
		$contextWarnings = [];
		$contextDiagnostics = [];
		$toolWarnings = [];
		$toolDiagnostics = [];
		try {
			$configuration = NeuronAgentConfiguration::fromArrays(
				$executionRequest->getAgentConfiguration(),
				$inputs
			);
			$contextResult = $this->contextProfileService->build(
				$configuration->getContextProfile(),
				$executionRequest
			);
			$configuration = $configuration->withInstructions(
				$this->contextInstructionsBuilder->build(
					$configuration->getInstructions(),
					$contextResult
				)
			);
			$contextWarnings = $contextResult->getWarnings();
			$contextDiagnostics = $contextResult->getDiagnostics();
			$toolSet = $this->toolProfileService->resolve(
				$configuration->getToolProfiles(),
				$executionRequest
			);
			$toolWarnings = $toolSet->getWarnings();
			$toolDiagnostics = [
				'profiles' => $configuration->getToolProfiles(),
				'tools' => $toolSet->getCatalog()->names()
			];
			$historyLease = $this->chatHistoryFactory->create($configuration, $executionRequest);
			$agent = $this->agentFactory->create($configuration, $executionRequest, $toolSet);
			if ($historyLease !== null) {
				$agent->setChatHistory($historyLease->getHistory());
			}

			if ($resume !== null) {
				$claim = $this->suspensionRepository->claim($resume->getResumeHandle());
				$result = $this->executeResume(
					$messageId,
					$resume,
					$claim,
					$agent,
					$toolSet,
					$historyLease,
					$eventMapper,
					$eventSink,
					$contextWarnings,
					$contextDiagnostics,
					$toolWarnings,
					$toolDiagnostics
				);
				$claimConsumed = true;
				return $result;
			}

			try {
				return $this->completeStream(
					$messageId,
					$agent->stream(new UserMessage($prompt)),
					$historyLease,
					$eventMapper,
					$eventSink,
					$contextWarnings,
					$contextDiagnostics,
					$toolWarnings,
					$toolDiagnostics
				);
			}
			catch (NeuronToolSuspensionException $e) {
				$historyLease?->discard();
				return $this->suspend(
					$messageId,
					$this->withContinuation($e->getSuspension(), $prompt, []),
					$eventSink,
					array_merge($contextWarnings, $toolWarnings),
					$contextDiagnostics,
					$toolDiagnostics
				);
			}
		}
		catch (\Throwable $e) {
			if ($claim !== null && !$claimConsumed) {
				$this->suspensionRepository->release($claim);
			}
			$historyLease?->discard();
			$this->emit($eventSink, 'error', [
				'message' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			]);
			$this->emit($eventSink, 'done', ['status' => AgentExecutionStatus::FAILED]);
			return $this->createFailureResult($messageId, $e, array_merge($contextWarnings, $toolWarnings));
		}
		finally {
			$historyLease?->release();
		}
	}

	/**
	 * @param array<int,string> $contextWarnings
	 * @param array<string,mixed> $contextDiagnostics
	 * @param array<int,string> $toolWarnings
	 * @param array<string,mixed> $toolDiagnostics
	 */
	private function executeResume(
		string $messageId,
		AgentResume $resume,
		AgentSuspensionClaim $claim,
		AgentInterface $agent,
		IAgentToolSet $toolSet,
		?NeuronChatHistoryLease $historyLease,
		NeuronExecutionEventMapper $eventMapper,
		?IAgentEventSink $eventSink,
		array $contextWarnings,
		array $contextDiagnostics,
		array $toolWarnings,
		array $toolDiagnostics
	): AgentExecutionResult {
		if (!$toolSet instanceof IAgentConfirmableToolSet) {
			throw new \RuntimeException('The current tool profiles do not support resuming confirmed actions.');
		}

		$suspension = $claim->getSuspension();
		$response = $this->resolveInteractionResponse($resume, $suspension);
		$continuation = $this->readContinuation($suspension);
		$prompt = trim((string)($continuation['prompt'] ?? ''));
		if ($prompt === '') {
			throw new \RuntimeException('Neuron tool suspension contains no original prompt.');
		}

		$call = $this->readSuspendedCall($suspension);
		$capability = $toolSet->getCatalog()->get($call->getName());
		if ($capability === null) {
			throw new \RuntimeException('The suspended tool is no longer available: ' . $call->getName());
		}

		$label = trim($capability->getTitle()) !== '' ? $capability->getTitle() : $call->getName();
		$toolPayload = $this->createToolEventPayload($call, $label);
		$this->suspensionRepository->consume($claim);
		$this->emit($eventSink, 'tool.started', $toolPayload);
		$result = $toolSet->resumeSuspension(
			$suspension,
			$response,
			[
				'source' => 'direct',
				'label' => $label,
				'iteration' => max(1, (int)($call->getMetadata()['iteration'] ?? 1)),
				'call_index' => max(1, (int)($call->getMetadata()['call_index'] ?? 1)),
				'trace' => [
					'prompt_text' => $prompt,
					'approved_interaction_request_id' => $response->getRequestId()
				]
			]
		);

		$this->emit(
			$eventSink,
			$result->isSuccess() ? 'tool.finished' : 'tool.failed',
			array_replace($toolPayload, [
				'result' => $result->getOutput(),
				'execution' => $result->toArray(),
				'error' => $result->getErrorMessage(),
				'error_code' => $result->getErrorCode()
			])
		);

		$completedTools = is_array($continuation['completed_tools'] ?? null)
			? $continuation['completed_tools']
			: [];
		$completedTools[] = $result->toArray();
		$messages = [new UserMessage($prompt)];
		foreach ($completedTools as $completedTool) {
			if (!is_array($completedTool)) {
				continue;
			}
			$completedResult = AgentToolResult::fromArray($completedTool);
			$completedCapability = $toolSet->getCatalog()->get($completedResult->getToolName());
			if ($completedCapability === null) {
				throw new \RuntimeException('A previously confirmed tool is no longer available: ' . $completedResult->getToolName());
			}
			$tool = $this->toolFactory->createOne($completedCapability, $toolSet);
			$tool->setCallId($completedResult->getCallId());
			$tool->setInputs($completedResult->getArguments());
			$tool->prepareExecution(
				max(1, (int)($completedResult->getMetadata()['iteration'] ?? 1)),
				max(1, (int)($completedResult->getMetadata()['call_index'] ?? 1))
			);
			$tool->applyExecutionResult($completedResult);
			$messages[] = new ToolCallMessage(null, [$tool]);
			$messages[] = new ToolResultMessage([$tool]);
		}

		try {
			return $this->completeStream(
				$messageId,
				$agent->stream($messages),
				$historyLease,
				$eventMapper,
				$eventSink,
				$contextWarnings,
				$contextDiagnostics,
				$toolWarnings,
				$toolDiagnostics,
				[$result->toArray()]
			);
		}
		catch (NeuronToolSuspensionException $e) {
			$historyLease?->discard();
			return $this->suspend(
				$messageId,
				$this->withContinuation($e->getSuspension(), $prompt, $completedTools),
				$eventSink,
				array_merge($contextWarnings, $toolWarnings),
				$contextDiagnostics,
				$toolDiagnostics
			);
		}
	}

	/**
	 * @param array<int,string> $contextWarnings
	 * @param array<string,mixed> $contextDiagnostics
	 * @param array<int,string> $toolWarnings
	 * @param array<string,mixed> $toolDiagnostics
	 * @param array<int,array<string,mixed>> $initialToolCalls
	 */
	private function completeStream(
		string $messageId,
		AgentHandler $handler,
		?NeuronChatHistoryLease $historyLease,
		NeuronExecutionEventMapper $eventMapper,
		?IAgentEventSink $eventSink,
		array $contextWarnings,
		array $contextDiagnostics,
		array $toolWarnings,
		array $toolDiagnostics,
		array $initialToolCalls = []
	): AgentExecutionResult {
		$toolCalls = $initialToolCalls;
		$cancelled = false;

		foreach ($handler->events() as $event) {
			if ($eventSink?->isCancelled() === true) {
				$cancelled = true;
				break;
			}
			foreach ($eventMapper->map($event) as $mappedEvent) {
				if (in_array($mappedEvent->getName(), ['tool.finished', 'tool.failed'], true)) {
					$toolCalls[] = $mappedEvent->getPayload();
				}
				$eventSink?->emit($mappedEvent);
			}
		}

		if ($cancelled) {
			$historyLease?->discard();
			return $this->createResult(
				$messageId,
				'',
				AgentExecutionStatus::PARTIAL,
				$toolCalls,
				array_merge($contextWarnings, $toolWarnings, ['Execution was cancelled by the event sink.'])
			);
		}

		$message = $handler->getMessage();
		$content = trim((string)($message->getContent() ?? ''));
		if ($content === '') {
			throw new \RuntimeException('Neuron AI completed without assistant content.');
		}

		$historyLease?->commit();
		$this->emit($eventSink, 'done', ['status' => AgentExecutionStatus::COMPLETED]);
		return $this->createResult(
			$messageId,
			$content,
			AgentExecutionStatus::COMPLETED,
			$toolCalls,
			array_merge($contextWarnings, $toolWarnings),
			$contextDiagnostics,
			$toolDiagnostics
		);
	}

	/** @param array<int,array<string,mixed>> $completedTools */
	private function withContinuation(
		AgentSuspension $suspension,
		string $prompt,
		array $completedTools
	): AgentSuspension {
		return new AgentSuspension(
			$suspension->getId(),
			$suspension->getStatus(),
			$suspension->getRequests(),
			array_replace($suspension->getState(), [
				self::CONTINUATION_STATE_KEY => [
					'prompt' => $prompt,
					'completed_tools' => $completedTools
				]
			]),
			$suspension->getCreatedAt(),
			array_replace($suspension->getMetadata(), ['runtime_id' => self::getRuntimeId()])
		);
	}

	/** @return array<string,mixed> */
	private function readContinuation(AgentSuspension $suspension): array {
		$value = $suspension->getState()[self::CONTINUATION_STATE_KEY] ?? null;
		return is_array($value) ? $value : [];
	}

	private function readSuspendedCall(AgentSuspension $suspension): AiToolCall {
		$value = $suspension->getState()['tool_call'] ?? null;
		if (!is_array($value)) {
			throw new \RuntimeException('Neuron tool suspension contains no valid tool call.');
		}
		return AiToolCall::fromArray($value);
	}

	private function resolveInteractionResponse(
		AgentResume $resume,
		AgentSuspension $suspension
	): AgentInteractionResponse {
		$requests = $suspension->getRequests();
		if (count($requests) !== 1 || !$requests[0] instanceof AgentInteractionRequest) {
			throw new \RuntimeException('Neuron tool suspension requires exactly one interaction request.');
		}
		$request = $requests[0];

		if ($resume->hasExplicitResponses()) {
			$responses = $resume->getResponses();
			if (count($responses) !== 1) {
				throw new \RuntimeException('Neuron tool resume requires exactly one explicit interaction response.');
			}
			$response = $responses[0];
			if ($response->getRequestId() !== $request->getId()) {
				throw new \RuntimeException('Neuron tool resume response does not match the pending interaction request.');
			}
			if (!in_array($response->getDecision(), [
				AgentInteractionResponse::DECISION_APPROVE,
				AgentInteractionResponse::DECISION_DENY
			], true)) {
				throw new \RuntimeException('Neuron tool resume accepts only approve or deny decisions.');
			}
			return $response;
		}

		$text = strtolower(trim($resume->getResponseText()));
		$approve = ['ja', 'yes', 'approve', 'approved', 'ich stimme zu', 'ich stimme zu.', 'zustimmen', 'ausfuehren', 'ausführen'];
		$deny = ['nein', 'no', 'deny', 'denied', 'ich lehne ab', 'ich lehne ab.', 'ablehnen', 'abbrechen'];
		if (in_array($text, $approve, true)) {
			return new AgentInteractionResponse(
				$request->getId(),
				AgentInteractionResponse::DECISION_APPROVE,
				[],
				$resume->getResponseText()
			);
		}
		if (in_array($text, $deny, true)) {
			return new AgentInteractionResponse(
				$request->getId(),
				AgentInteractionResponse::DECISION_DENY,
				[],
				$resume->getResponseText()
			);
		}

		$feedback = trim($resume->getResponseText());
		if ($feedback === '') {
			throw new \RuntimeException('Please approve, deny, or describe the requested change.');
		}

		return new AgentInteractionResponse(
			$request->getId(),
			AgentInteractionResponse::DECISION_DENY,
			[],
			$feedback
		);
	}

	/** @return array<string,mixed> */
	private function createToolEventPayload(AiToolCall $call, string $label): array {
		return [
			'id' => $call->getId(),
			'call_id' => $call->getId(),
			'name' => $call->getName(),
			'tool' => $call->getName(),
			'label' => $label,
			'arguments' => $call->getArguments(),
			'args' => $call->getArguments(),
			'iteration' => max(1, (int)($call->getMetadata()['iteration'] ?? 1)),
			'call_index' => max(1, (int)($call->getMetadata()['call_index'] ?? 1))
		];
	}

	/**
	 * @param array<int,string> $warnings
	 * @param array<string,mixed> $contextDiagnostics
	 * @param array<string,mixed> $toolDiagnostics
	 */
	private function suspend(
		string $messageId,
		AgentSuspension $suspension,
		?IAgentEventSink $eventSink,
		array $warnings,
		array $contextDiagnostics,
		array $toolDiagnostics
	): AgentExecutionResult {
		$resumeHandle = $this->suspensionRepository->create($suspension, self::SUSPENSION_TTL_SECONDS);
		$requests = array_map(
			static fn(AgentInteractionRequest $request): array => $request->toArray(),
			$suspension->getRequests()
		);
		$payload = [
			'status' => $suspension->getStatus(),
			'interaction_requests' => $requests,
			'resume_handle' => $resumeHandle
		];
		$this->emit($eventSink, 'agent.interaction.required', $payload);
		$this->emit($eventSink, 'done', ['status' => $suspension->getStatus()]);

		$output = [
			self::ASSISTANT_OUTPUT_ID => array_replace($payload, [
				'tool_calls' => [],
				'message' => null
			])
		];
		$agentResult = new AgentResult(
			$suspension->getStatus(),
			AgentState::empty()->withSuspension(new AgentSuspensionState(
				true,
				$suspension->getStatus(),
				$suspension->getRequests(),
				$resumeHandle
			)),
			$output,
			[
				'runtime' => self::getRuntimeId(),
				'context_profile' => $contextDiagnostics,
				'tool_profiles' => $toolDiagnostics
			]
		);
		return new AgentExecutionResult($output, $warnings, $agentResult);
	}

	/** @param array<string,mixed> $payload */
	private function emit(?IAgentEventSink $eventSink, string $name, array $payload = []): void {
		if ($eventSink === null || $eventSink->isCancelled()) {
			return;
		}
		$eventSink->emit(new AgentExecutionEvent($name, $payload));
	}

	/**
	 * @param array<int,array<string,mixed>> $toolCalls
	 * @param array<int,string> $warnings
	 */
	private function createResult(
		string $messageId,
		string $content,
		string $status,
		array $toolCalls = [],
		array $warnings = [],
		array $contextDiagnostics = [],
		array $toolDiagnostics = []
	): AgentExecutionResult {
		$message = [
			'id' => $messageId,
			'role' => 'assistant',
			'content' => $content
		];
		$output = [
			self::ASSISTANT_OUTPUT_ID => [
				'message' => $message,
				'tool_calls' => $toolCalls,
				'status' => $status
			]
		];
		$agentResult = new AgentResult(
			$status,
			AgentState::empty(),
			$output,
			[
				'runtime' => self::getRuntimeId(),
				'context_profile' => $contextDiagnostics,
				'tool_profiles' => $toolDiagnostics
			]
		);
		return new AgentExecutionResult($output, $warnings, $agentResult);
	}

	/** @param array<int,string> $warnings */
	private function createFailureResult(
		string $messageId,
		\Throwable $error,
		array $warnings = []
	): AgentExecutionResult {
		$output = [
			self::ASSISTANT_OUTPUT_ID => [
				'error' => $error->getMessage(),
				'tool_calls' => [],
				'status' => AgentExecutionStatus::FAILED
			]
		];
		$agentResult = new AgentResult(
			AgentExecutionStatus::FAILED,
			AgentState::empty(),
			$output,
			[
				'runtime' => self::getRuntimeId(),
				'error_type' => get_class($error)
			]
		);
		return new AgentExecutionResult($output, $warnings, $agentResult);
	}

	private function withExecutionContext(
		AgentExecutionRequest $request,
		string $messageId,
		string $prompt
	): AgentExecutionRequest {
		$context = $request->getContext();
		$agentConfiguration = $request->getAgentConfiguration();
		$configGroup = $this->readFirstString($context, ['config_group', 'chatbot_config_group']);
		if ($configGroup === '') {
			$configGroup = $this->readFirstString($agentConfiguration, ['agent_config_group', 'config_group']);
		}
		$configName = $this->readFirstString($context, ['config_name', 'chatbot_config_name']);
		if ($configName === '') {
			$configName = $this->readFirstString($agentConfiguration, ['agent_config_name', 'agent_id', 'id']);
		}
		$chatbotKey = $this->readFirstString($context, ['chatbot_key']);
		if ($chatbotKey === '') {
			$chatbotKey = $configGroup !== '' && $configName !== ''
				? $configGroup . ':' . $configName
				: ($configName !== '' ? $configName : $configGroup);
		}

		return new AgentExecutionRequest(
			$request->getAgentConfiguration(),
			$request->getInputs(),
			array_replace($context, [
				'runtime_id' => self::getRuntimeId(),
				'turn_id' => $this->readFirstString($context, ['turn_id', 'chat_turn_id', 'message_id']) ?: $messageId,
				'chat_turn_id' => $this->readFirstString($context, ['chat_turn_id', 'turn_id', 'message_id']) ?: $messageId,
				'message_id' => $messageId,
				'chatbot_key' => $chatbotKey,
				'config_group' => $configGroup,
				'config_name' => $configName,
				'prompt_text' => $prompt
			])
		);
	}

	/** @param array<string,mixed> $inputs */
	private function readResume(array $inputs): ?AgentResume {
		$value = $inputs['resume'] ?? null;
		if ($value === null || $value === '' || $value === []) {
			return null;
		}
		if (!is_array($value)) {
			throw new \InvalidArgumentException('Neuron AI resume input must be an associative array.');
		}
		return AgentResume::fromArray($value);
	}

	/** @param array<string,mixed> $values @param array<int,string> $keys */
	private function readFirstString(array $values, array $keys): string {
		foreach ($keys as $key) {
			$value = $this->readString($values, $key);
			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	/** @param array<string,mixed> $values */
	private function readString(array $values, string $key): string {
		$value = $values[$key] ?? '';
		return is_scalar($value) || $value === null ? trim((string)$value) : '';
	}
}
