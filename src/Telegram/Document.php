<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Http\Client\Core\MimeType;

class Document extends Output
{
    /**
     * Create a new document output.
     *
     * @param  string|null  $extension  The extension the file must have, such as "csv" or "md".
     */
    public function __construct(protected ?string $extension = null) {}

    /**
     * Create a document of the given type, such as "csv", "md", "json" or "html".
     */
    public static function as(string $extension): static
    {
        return new static(ltrim($extension, '.'));
    }

    /**
     * Get the structured output schema the model fills in.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->description($this->extension
                ? "The file name, ending in .{$this->extension}."
                : 'The file name, with an extension that matches its content.')->required(),
            'content' => $schema->string()->description('The full content of the text file.')->required(),
            'caption' => $schema->string()->description('A short caption, at most 1024 characters.')->nullable(),
        ];
    }

    /**
     * Send the generated file.
     */
    public function send(array $data, Chat $chat): void
    {
        $filename = basename(trim((string) ($data['filename'] ?? 'file'))) ?: 'file';

        if ($this->extension !== null && ! str_ends_with(strtolower($filename), '.'.strtolower($this->extension))) {
            $filename .= '.'.$this->extension;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        Sender::to($chat)->call('sendDocument', array_filter([
            ...$chat->parameters(),
            'document' => new Upload((string) ($data['content'] ?? ''), $filename, MimeType::fromExtension($extension) ?? 'text/plain'),
            'caption' => isset($data['caption']) && $data['caption'] !== '' ? $this->limit($data['caption'], 1024) : null,
            'reply_markup' => $this->replyMarkup,
        ], fn ($value) => $value !== null));
    }
}
