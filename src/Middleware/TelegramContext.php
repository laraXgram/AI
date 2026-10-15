<?php

namespace LaraGram\Ai\Middleware;

use Closure;
use LaraGram\Ai\PendingStep;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Container\Container;
use LaraGram\Request\Request;

class TelegramContext
{
    /**
     * Create a new middleware instance.
     *
     * @param  bool  $language  Whether to ask the model to answer in the user's Telegram language.
     * @param  bool  $formatting  Whether to tell the model which Markdown Telegram shows.
     */
    public function __construct(
        protected bool $language = true,
        protected bool $formatting = true,
    ) {}

    /**
     * Add the Telegram user and chat of the current update to the instructions of the step.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        if (($context = $this->describe()) !== null) {
            $step = $step->withInstructions(trim(($step->instructions ?? '')."\n\n".$context));
        }

        return $next($step);
    }

    /**
     * Describe the Telegram context of the current update.
     */
    public function describe(): ?string
    {
        $container = Container::getInstance();
        $request = $container->bound('request') ? $container->make('request') : null;

        if (! $request instanceof Request || ($chat = Chat::fromRequest($request)) === null) {
            return null;
        }

        $user = Chat::userOf($request);
        $message = $request->message ?? $request->business_message ?? $request->callback_query?->message;

        $lines = ['This conversation takes place in Telegram.'];

        if ($user !== null) {
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

            $lines[] = 'The user is '.($name !== '' ? $name : 'unnamed')
                .(isset($user->username) ? ' (@'.$user->username.')' : '')
                .', Telegram user ID '.$user->id.'.';

            if ($this->language && isset($user->language_code)) {
                $lines[] = "The user's Telegram language is \"{$user->language_code}\"; answer in the language the user writes in, or in that language.";
            }
        }

        $lines[] = match (true) {
            $chat->inlineQueryId !== null => 'The answer will be shown as an inline query result.',
            $chat->isPrivate() => 'This is a private chat with the user.',
            default => 'This is a '.($message->chat->type ?? 'group').' chat'.(isset($message->chat->title) ? ' named "'.$message->chat->title.'"' : '').'.',
        };

        if (isset($message->reply_to_message)) {
            $replied = $message->reply_to_message;

            $lines[] = 'The user is replying to this message'
                .(isset($replied->from->first_name) ? ' from '.$replied->from->first_name : '')
                .': "'.mb_strimwidth((string) ($replied->text ?? $replied->caption ?? ''), 0, 1000, '…').'"';
        }

        if ($this->formatting) {
            $lines[] = 'Write in Markdown: bold, italic, inline code, code blocks, links, lists, quotes and tables are shown to the user. Keep messages concise and readable on a phone.';
        }

        return implode("\n", $lines);
    }
}
