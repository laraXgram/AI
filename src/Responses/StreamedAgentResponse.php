<?php

namespace LaraGram\Ai\Responses;

use LaraGram\Support\Collection;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Streaming\Events\Citation;
use LaraGram\Ai\Streaming\Events\ReasoningDelta;
use LaraGram\Ai\Streaming\Events\StreamEnd;
use LaraGram\Ai\Streaming\Events\StreamEvent;
use LaraGram\Ai\Streaming\Events\TextDelta;
use LaraGram\Ai\Streaming\Events\ToolApprovalRequest;
use LaraGram\Ai\Streaming\Events\ToolCall;
use LaraGram\Ai\Streaming\Events\ToolResult;

class StreamedAgentResponse extends AgentResponse
{
    /** @var Collection<int, StreamEvent> */
    public Collection $events;

    /**
     * @param  Collection<int, StreamEvent>  $events
     */
    public function __construct(string $invocationId, Collection $events, Meta $meta)
    {
        parent::__construct(
            $invocationId,
            TextDelta::combine($events),
            StreamEnd::combineUsage($events),
            $meta,
        );

        $this->withToolCallsAndResults(
            toolCalls: $events->whereInstanceOf(ToolCall::class)->map->toolCall,
            toolResults: $events->whereInstanceOf(ToolResult::class)
                ->reject(fn (ToolResult $event): bool => $event->preliminary)
                ->map->toolResult,
        );

        $this->events = $events;

        $this->reasoning = ReasoningDelta::combine($events);

        // A streamed run only ever sees its citations as events, not on the parsed body...
        $this->meta->citations = Citation::combine($events);

        $this->withPendingApprovals(
            $events->whereInstanceOf(ToolApprovalRequest::class)
                ->flatMap(fn (ToolApprovalRequest $event) => $event->pendingApprovals)
                ->values()
        );

        $this->withSteps(
            $events->whereInstanceOf([StreamEnd::class, ToolApprovalRequest::class])->last()?->steps ?? new Collection
        );
    }
}
