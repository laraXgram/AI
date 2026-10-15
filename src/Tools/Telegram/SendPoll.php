<?php

namespace LaraGram\Ai\Tools\Telegram;

use LaraGram\Ai\Telegram\Poll;
use LaraGram\Ai\Telegram\Quiz;
use LaraGram\Ai\Tools\Request;
use LaraGram\Contracts\JsonSchema\JsonSchema;

class SendPoll extends TelegramTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Send a poll to the current chat. When a correct option is given, the poll is a quiz.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        return $this->attempt(function () use ($request) {
            $chat = $this->chat()->withoutReply();
            $options = array_values((array) $request['options']);

            if (isset($request['correct_option'])) {
                Quiz::questions()->options(count($options))->send(['questions' => [[
                    'question' => $request['question'],
                    'options' => $options,
                    'correct' => [(int) $request['correct_option']],
                    'explanation' => $request['explanation'] ?? null,
                ]]], $chat);
            } else {
                Poll::make()->multipleAnswers((bool) ($request['multiple_answers'] ?? false))
                    ->send(['question' => $request['question'], 'options' => $options], $chat);
            }

            return 'Poll sent.';
        });
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->description('The question, at most 300 characters.')->required(),
            'options' => $schema->array()->items($schema->string())->min(2)->max(10)->required(),
            'multiple_answers' => $schema->boolean()->nullable(),
            'correct_option' => $schema->integer()->description('The 0-based index of the correct option, for a quiz.')->nullable(),
            'explanation' => $schema->string()->description('Why the quiz answer is correct.')->nullable(),
        ];
    }
}
