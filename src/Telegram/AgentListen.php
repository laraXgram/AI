<?php

namespace LaraGram\Ai\Telegram;

use LaraGram\Ai\Contracts\Agent;
use LaraGram\Ai\Contracts\HasStructuredOutput;
use LaraGram\Ai\Transcription;
use LaraGram\Container\Container;
use LaraGram\Listening\Listener;
use LaraGram\Request\Request;

class AgentListen
{
    /**
     * Register a listen that answers messages with the given agent.
     *
     * Without a pattern every message is answered; with one, only text matching it.
     *
     * @return \LaraGram\Listening\Listen
     */
    public static function register(Listener $listener, string|Agent $agent, ?string $pattern = null)
    {
        $action = fn (Request $request) => static::handle($agent, $request);

        return $pattern === null
            ? $listener->onMessage($action)
            : $listener->onText($pattern, $action);
    }

    /**
     * Prompt the agent with the message of the update and return its response.
     *
     * The message text or caption is the prompt; photos, documents and videos
     * are attached, and voice messages are transcribed first. Agents that
     * remember conversations continue the last conversation of the sender.
     */
    public static function handle(string|Agent $agent, Request $request): mixed
    {
        $agent = is_string($agent) ? Container::getInstance()->make($agent) : clone $agent;

        $message = $request->message ?? $request->business_message ?? $request->edited_message;

        $prompt = trim((string) ($message->text ?? $message->caption ?? ''));
        $attachments = [];

        if ($request->hasFile() && ($files = $request->file()) && $files->isNotEmpty()) {
            if (in_array($files->type(), ['voice', 'audio'], true)) {
                $transcript = trim(Transcription::fromUpload($files)->generate()->text);

                $prompt = trim($prompt."\n\n".$transcript);
            } elseif (! in_array($files->type(), ['sticker'], true)) {
                $attachments[] = $files;
            }
        }

        if ($prompt === '' && $attachments === []) {
            return null;
        }

        if (method_exists($agent, 'continueLastConversation') && method_exists($agent, 'hasConversationParticipant') && ! $agent->hasConversationParticipant()) {
            $chat = $message->chat ?? null;

            $participant = $chat !== null && ($chat->type ?? 'private') !== 'private'
                ? $chat
                : (Chat::userOf($request) ?? $chat);

            if ($participant !== null) {
                $agent->continueLastConversation($participant);
            }
        }

        return $agent instanceof HasStructuredOutput
            ? $agent->prompt($prompt, $attachments)
            : $agent->stream($prompt, $attachments);
    }
}
