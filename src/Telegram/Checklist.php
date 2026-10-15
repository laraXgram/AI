<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;

class Checklist extends Output
{
    /**
     * Create a new checklist output.
     */
    public function __construct(
        protected int $maxTasks = 10,
        protected bool $othersCanAddTasks = false,
        protected bool $othersCanMarkTasksAsDone = false,
    ) {}

    /**
     * Set the largest number of tasks (1-30).
     *
     * @return $this
     */
    public function max(int $tasks): static
    {
        $this->maxTasks = max(1, min(30, $tasks));

        return $this;
    }

    /**
     * Let other users add tasks to the checklist.
     *
     * @return $this
     */
    public function othersCanAddTasks(bool $value = true): static
    {
        $this->othersCanAddTasks = $value;

        return $this;
    }

    /**
     * Let other users mark tasks as done.
     *
     * @return $this
     */
    public function othersCanMarkTasksAsDone(bool $value = true): static
    {
        $this->othersCanMarkTasksAsDone = $value;

        return $this;
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('The title of the checklist.')->required(),
            'tasks' => $schema->array()
                ->items($schema->string()->description('A task, at most 100 characters.'))
                ->min(1)->max($this->maxTasks)
                ->required(),
        ];
    }

    /**
     * Send a checklist, or a rich message listing the tasks when the chat has no business connection.
     */
    public function send(array $data, Chat $chat): void
    {
        $tasks = array_values(array_filter(array_map(fn ($task) => $this->limit($task, 100), (array) ($data['tasks'] ?? []))));
        $title = $this->limit($data['title'] ?? '', 255);

        if ($chat->businessConnectionId === null) {
            $markdown = '## '.$title."\n\n".implode("\n", array_map(fn ($task) => '- [ ] '.$task, $tasks));

            Sender::to($chat)->markdown($markdown, $this->replyMarkup);

            return;
        }

        Sender::to($chat)->call('sendChecklist', [
            ...$chat->parameters(),
            'checklist' => array_filter([
                'title' => $title,
                'tasks' => array_map(fn ($task, $index) => ['id' => $index + 1, 'text' => $task], $tasks, array_keys($tasks)),
                'others_can_add_tasks' => $this->othersCanAddTasks ?: null,
                'others_can_mark_tasks_as_done' => $this->othersCanMarkTasksAsDone ?: null,
            ]),
            'reply_markup' => $this->replyMarkup,
        ]);
    }
}
