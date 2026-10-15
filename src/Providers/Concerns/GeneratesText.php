<?php

namespace LaraGram\Ai\Providers\Concerns;

use Closure;
use LaraGram\JsonSchema\JsonSchemaTypeFactory;
use LaraGram\Support\Str;
use LaraGram\Ai\Ai;
use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\Conversational;
use LaraGram\Ai\Contracts\ConversationStore;
use LaraGram\Ai\Contracts\HasSkills;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Contracts\TelegramOutput;
use LaraGram\Ai\Contracts\HasTools;
use LaraGram\Ai\Contracts\Tool;
use LaraGram\Ai\Events\AgentFailed;
use LaraGram\Ai\Events\AgentPrompted;
use LaraGram\Ai\Events\PromptingAgent;
use LaraGram\Ai\Events\ToolApprovalRequested;
use LaraGram\Ai\Events\ToolApprovalResolved;
use LaraGram\Ai\Exceptions\ApprovalNotResumableException;
use LaraGram\Ai\Gateway\RunContext;
use LaraGram\Ai\Gateway\TextGenerationOptions;
use LaraGram\Ai\Messages\UserMessage;
use LaraGram\Ai\Middleware\RememberConversation;
use LaraGram\Ai\Prompts\AgentPrompt;
use LaraGram\Ai\Providers\Tools\ToolSearch;
use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Responses\StructuredAgentResponse;
use LaraGram\Ai\Responses\StructuredTextResponse;
use LaraGram\Ai\Responses\TextResponse;
use LaraGram\Ai\Tools\AgentTool;
use LaraGram\Ai\Tools\LoadSkill;
use LaraGram\Ai\Tools\McpServerTool;
use LaraGram\Ai\Tools\McpTool;
use Throwable;

use function LaraGram\Ai\pipeline;

trait GeneratesText
{
    use ResumesToolApprovals;

    /**
     * Invoke the given agent.
     */
    public function prompt(AgentPrompt $prompt): AgentResponse
    {
        $invocationId = $prompt->invocationId ?? (string) Str::uuid7();

        $resolvedApprovalResults = null;

        try {
            $response = pipeline()
                ->send($prompt)
                ->through($this->gatherMiddlewareFor($prompt->agent))
                ->then(function (AgentPrompt $prompt) use ($invocationId, &$resolvedApprovalResults): TextResponse {
                    $this->events->dispatch(new PromptingAgent($invocationId, $prompt));

                    $agent = $prompt->agent;

                    $messages = $this->withoutForeignReplayBlocks([
                        ...($prompt->messages ?? []),
                        ...($agent instanceof Conversational ? $agent->messages() : []),
                    ]);

                    if (! $prompt->hasApprovalDecisions()) {
                        $messages[] = new UserMessage($prompt->prompt, $prompt->attachments->all());
                    }

                    $schema = $agent instanceof HasStructuredOutput ? $agent->schema(new JsonSchemaTypeFactory) : null;

                    [$schema, $output] = $schema instanceof TelegramOutput
                        ? [$schema->schema(new JsonSchemaTypeFactory), $schema]
                        : [$schema, null];

                    $response = $this->textGenerationLoop()->generate(
                        $this,
                        $prompt->model,
                        (string) $agent->instructions(),
                        $messages,
                        $this->resolveTools($prompt),
                        $schema,
                        TextGenerationOptions::forAgent($agent),
                        $prompt->timeout,
                        $this->resumableApprovalFor($prompt),
                        $this->approvalResultRecorderFor($prompt, $resolvedApprovalResults),
                        $this->runContextFor($invocationId, $prompt),
                    );

                    if ($response->hasPendingApprovals()) {
                        $this->throwIfNotResumable($prompt);
                    }

                    $agentResponse = $response instanceof StructuredTextResponse
                        ? (new StructuredAgentResponse($invocationId, $response->structured, $response->text, $response->usage, $response->meta))
                            ->withTelegramOutput($output)
                            ->withMessages($response->messages)
                            ->withToolCallsAndResults($response->toolCalls, $response->toolResults)
                            ->withSteps($response->steps)
                            ->withReasoning($response->reasoning)
                            ->withRawResponse($response->raw)
                        : (new AgentResponse($invocationId, $response->text, $response->usage, $response->meta))
                            ->withMessages($response->messages)
                            ->withToolCallsAndResults($response->toolCalls, $response->toolResults)
                            ->withSteps($response->steps)
                            ->withReasoning($response->reasoning)
                            ->withRawResponse($response->raw);

                    $agentResponse->withPendingApprovals($response->pendingApprovals);

                    return $agentResponse;
                });
        } catch (Throwable $exception) {
            $this->recordAgentFailure($invocationId, $prompt, $exception);

            throw $exception;
        }

        $this->events->dispatch(
            new AgentPrompted($invocationId, $prompt, $response)
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

        return $response;
    }

    /**
     * Gather the internal run middleware for the given agent.
     */
    protected function gatherMiddlewareFor(Agent $agent): array
    {
        $middleware = Ai::hasFakeGatewayFor($agent::class) ? [function (AgentPrompt $prompt, Closure $next) {
            Ai::recordPrompt($prompt);

            return $next($prompt);
        }] : [];

        if (RememberConversation::appliesTo($agent)) {
            $middleware[] = new RememberConversation(resolve(ConversationStore::class), $this);
        }

        return $middleware;
    }

    /**
     * Resolve the tools for the given prompt, wrapping any agent instances as tools.
     */
    protected function resolveTools(AgentPrompt $prompt): array
    {
        return array_map(
            fn ($tool) => $this->resolveTool($tool),
            $prompt->tools ?? $this->declaredTools($prompt->agent),
        );
    }

    /**
     * Get the tools the agent declares, including the tool that loads its skills.
     */
    protected function declaredTools(Agent $agent): array
    {
        $tools = $agent instanceof HasTools ? [...$agent->tools()] : [];

        return $agent instanceof HasSkills ? LoadSkill::mergeInto($tools, $agent) : $tools;
    }

    /**
     * Resolve a tool returned by the agent into a native tool instance when needed.
     */
    protected function resolveTool(mixed $tool): mixed
    {
        return match (true) {
            $tool instanceof Agent => new AgentTool($tool),
            $tool instanceof Tool => $tool,
            $tool instanceof ToolSearch => $tool->withTools(
                array_map(fn ($nested) => $this->resolveTool($nested), $tool->tools),
            ),
            McpTool::supports($tool) => new McpTool($tool),
            McpServerTool::supports($tool) => new McpServerTool($tool),
            default => $tool,
        };
    }

    /**
     * Build the context that identifies this run and reports its step and tool events.
     */
    protected function runContextFor(string $invocationId, AgentPrompt $prompt): RunContext
    {
        return tap(
            new RunContext($invocationId, $prompt->agent, $this, $prompt->model, $this->events),
            fn (RunContext $context) => $prompt->setRunContext($context),
        );
    }

    /**
     * Dispatch the terminal failure event for a run, unless the caller may still retry it against another provider.
     */
    protected function recordAgentFailure(string $invocationId, AgentPrompt $prompt, Throwable $exception, bool $retryable = true): void
    {
        // A failoverable exception is only terminal once the caller has run out of providers to try...
        if ($retryable && $prompt->willRetry($exception)) {
            return;
        }

        $this->events->dispatch(
            new AgentFailed($invocationId, $prompt, $exception)
        );
    }

    /**
     * Throw when a pause has surfaced on a prompt that cannot be resumed from persisted or replayed history.
     */
    protected function throwIfNotResumable(AgentPrompt $prompt): void
    {
        // An ad-hoc history replays from the client, even when the first turn carried no messages...
        if (! $prompt->agent instanceof Conversational && $prompt->messages === null) {
            throw ApprovalNotResumableException::make();
        }
    }
}
