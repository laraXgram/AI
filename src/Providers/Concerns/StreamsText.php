<?php

namespace LaraGram\Ai\Providers\Concerns;

use LaraGram\Support\Str;
use InvalidArgumentException;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use LaraGram\Ai\Events\AgentStreamed;
use LaraGram\Ai\Events\StreamingAgent;
use LaraGram\Ai\Events\ToolApprovalRequested;
use LaraGram\Ai\Events\ToolApprovalResolved;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\Messages\UserMessage;
use LaraGram\Ai\Middleware\RememberConversation;
use LaraGram\Ai\Prompts\AgentPrompt;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\StreamableAgentResponse;
use LaraGram\Ai\Responses\StreamedAgentResponse;
use LaraGram\Ai\Streaming\Events\ToolApprovalRequest;
use Throwable;

use function LaraGram\Ai\pipeline;

trait StreamsText
{
    use ResumesToolApprovals;

    /**
     * Stream the response from the given agent.
     */
    public function stream(AgentPrompt $prompt): StreamableAgentResponse
    {
        $invocationId = $prompt->invocationId ?? (string) Str::uuid7();

        $resolvedApprovalResults = null;

        try {
            $response = pipeline()
                ->send($prompt)
                ->through($this->gatherMiddlewareFor($prompt->agent))
                ->then(function (AgentPrompt $prompt) use ($invocationId, &$resolvedApprovalResults): StreamableAgentResponse {
                    $agent = $prompt->agent;

                    if ($agent instanceof HasStructuredOutput) {
                        throw new InvalidArgumentException('Streaming structured output is not currently supported.');
                    }

                    $meta = new Meta($this->name(), $prompt->model);

                    $messages = $this->withoutForeignReplayBlocks([
                        ...($prompt->messages ?? []),
                        ...($agent instanceof Conversational ? $agent->messages() : []),
                    ]);

                    if (! $prompt->hasApprovalDecisions()) {
                        $messages[] = new UserMessage($prompt->prompt, $prompt->attachments->all());
                    }

                    $tools = $this->resolveTools($prompt);
                    $approval = $this->resumableApprovalFor($prompt);
                    $recordApprovalResults = $this->approvalResultRecorderFor($prompt, $resolvedApprovalResults);

                    // Validate eagerly so a mismatch throws before the stream begins, then thread the result into stream() so it isn't re-validated there...
                    $validatedApproval = $approval !== null
                        ? $this->textGenerationLoop()->validateApproval($approval, $messages, $tools)
                        : null;

                    $streamable = null;

                    // The response owns the "has anything reached the consumer" flag so this failure check and the caller's failover decision can never drift apart...
                    $streamable = new StreamableAgentResponse(
                        $invocationId,
                        function () use ($invocationId, $prompt, $agent, $messages, $tools, $approval, $recordApprovalResults, $validatedApproval, &$streamable) {
                            $this->events->dispatch(new StreamingAgent($invocationId, $prompt));

                            try {
                                foreach ($this->textGenerationLoop()->stream(
                                    $invocationId,
                                    $this,
                                    $prompt->model,
                                    (string) $agent->instructions(),
                                    $messages,
                                    $tools,
                                    null,
                                    TextGenerationOptions::forAgent($agent),
                                    $prompt->timeout,
                                    $approval,
                                    $recordApprovalResults,
                                    $validatedApproval,
                                    $this->runContextFor($invocationId, $prompt),
                                ) as $event) {
                                    if ($event instanceof ToolApprovalRequest) {
                                        $this->throwIfNotResumable($prompt);
                                    }

                                    yield $event;
                                }
                            } catch (Throwable $exception) {
                                $this->recordAgentFailure($invocationId, $prompt, $exception, retryable: ! $streamable->hasYielded());

                                throw $exception;
                            }
                        },
                        $meta,
                    );

                    // Surfaced before iteration because the remembering middleware only records it once the stream has drained...
                    if (RememberConversation::appliesTo($agent)) {
                        /** @var Agent&RemembersConversationsContract $agent */
                        if ($agent->currentConversation() !== null) {
                            $streamable->withinConversation(
                                $agent->currentConversation(),
                                $agent->conversationParticipant(),
                            );
                        }
                    }

                    return $streamable;
                });
        } catch (Throwable $exception) {
            $this->recordAgentFailure($invocationId, $prompt, $exception);

            throw $exception;
        }

        return $response->then(function (StreamedAgentResponse $response) use ($invocationId, $prompt, &$resolvedApprovalResults): void {
            $this->events->dispatch(
                new AgentStreamed($invocationId, $prompt, $response)
            );

            if ($response->hasPendingApprovals()) {
                $this->events->dispatch(new ToolApprovalRequested(
                    $invocationId,
                    $prompt->agent,
                    $response->pendingApprovals,
                    $response->conversationId,
                    $response->conversationUser,
                ));
            }

            if ($resolvedApprovalResults !== null) {
                $this->events->dispatch(new ToolApprovalResolved(
                    $invocationId,
                    $prompt->agent,
                    $resolvedApprovalResults,
                    $response->conversationId,
                    $response->conversationUser,
                ));
            }
        });
    }
}
