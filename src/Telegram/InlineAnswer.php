<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Ai\Telegram\Formatting\Inline;
use LaraGram\Ai\Telegram\Formatting\Markdown;
use LaraGram\Ai\Telegram\Formatting\TelegramHtml;
use LaraGram\Support\Str;

class InlineAnswer
{
    /**
     * Answer the chat's inline query with a single article holding the given text.
     */
    public static function text(Chat $chat, string $markdown, ?string $title = null, int $cacheTime = 0): void
    {
        static::articles($chat, [[
            'title' => $title ?? static::title($markdown),
            'description' => Str::limit(static::plain($markdown), 120),
            'text' => $markdown,
        ]], $cacheTime);
    }

    /**
     * Answer the chat's inline query with the given articles.
     *
     * Each article has a "title", an optional "description", and the Markdown "text" sent when it is chosen.
     *
     * @param  array<int, array{title: string, description?: string|null, text: string}>  $articles
     */
    public static function articles(Chat $chat, array $articles, int $cacheTime = 0, bool $personal = true): void
    {
        if ($chat->inlineQueryId === null) {
            throw new TelegramException('Inline results may only answer an inline query.');
        }

        $results = [];

        foreach (array_values($articles) as $index => $article) {
            $results[] = array_filter([
                'type' => 'article',
                'id' => (string) ($index + 1),
                'title' => Str::limit((string) $article['title'], 100),
                'description' => isset($article['description']) ? Str::limit((string) $article['description'], 200) : null,
                'input_message_content' => [
                    'message_text' => TelegramHtml::chunks((string) $article['text'])[0] ?? TelegramHtml::escape((string) $article['title']),
                    'parse_mode' => 'HTML',
                    'link_preview_options' => ['is_disabled' => true],
                ],
            ], fn ($value) => $value !== null);
        }

        $chat->messenger()->call('answerInlineQuery', [
            'inline_query_id' => $chat->inlineQueryId,
            'results' => array_slice($results, 0, 50),
            'cache_time' => $cacheTime,
            'is_personal' => $personal,
        ]);
    }

    /**
     * Derive a title from the first line of the Markdown.
     */
    protected static function title(string $markdown): string
    {
        return Str::limit(strtok(static::plain($markdown), "\n") ?: '…', 60);
    }

    /**
     * Get the plain text of the Markdown.
     */
    protected static function plain(string $markdown): string
    {
        $text = [];

        foreach (Markdown::parse($markdown) as $block) {
            $text[] = isset($block['inlines']) ? Inline::plain($block['inlines']) : ($block['text'] ?? '');
        }

        return trim(implode("\n", array_filter($text)));
    }
}
