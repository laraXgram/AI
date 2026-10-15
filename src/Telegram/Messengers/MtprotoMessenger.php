<?php

namespace LaraGram\Ai\Telegram\Messengers;

use LaraGram\Ai\Telegram\TelegramException;
use LaraGram\Ai\Telegram\Upload;
use LaraGram\Container\Container;
use Throwable;

class MtprotoMessenger extends Messenger
{
    /**
     * The Bot API methods the MTProto transport can deliver.
     *
     * @var string[]
     */
    protected const METHODS = [
        'sendMessage', 'editMessageText', 'sendRichMessage', 'editMessageReplyMarkup', 'sendChatAction',
        'sendPhoto', 'sendVoice', 'sendAudio', 'sendVideo', 'sendDocument', 'sendMediaGroup',
        'sendPoll', 'sendLocation', 'sendVenue', 'sendChecklist', 'sendDice',
        'answerInlineQuery', 'answerCallbackQuery', 'deleteMessage', 'setMessageReaction',
    ];

    /**
     * Call a Bot API method through the chat's MTProto session.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws \LaraGram\Ai\Telegram\TelegramException
     */
    public function call(string $method, array $parameters = []): mixed
    {
        if (! $this->supports($method)) {
            throw new TelegramException("The [{$method}] method is not available over MTProto.");
        }

        $client = Container::getInstance()->make('mtproto.manager')->client($this->chat->session ?? 'default');

        $peer = $parameters['chat_id'] ?? $this->chat->id;
        $common = $this->commonParameters($parameters);
        $caption = $parameters['caption'] ?? null;

        try {
            $result = match ($method) {
                'sendMessage' => $client->sendMessage(...['peer' => $peer, 'message' => (string) $parameters['text'], ...$common, ...$this->parseMode($parameters)]),
                'editMessageText' => $client->editMessage($peer, (int) $parameters['message_id'], (string) $parameters['text'], [...$common, ...$this->parseMode($parameters)]),
                'editMessageReplyMarkup' => $client->editMessage($peer, (int) $parameters['message_id'], null, $common),
                'sendRichMessage' => $client->sendRichMessage($peer, (string) ($parameters['rich_message']['html'] ?? ''), 'html', array_filter([
                    'rtl' => $parameters['rich_message']['is_rtl'] ?? null,
                    ...$common,
                ])),
                'sendChatAction' => $client->sendChatAction($peer, (string) ($parameters['action'] ?? 'typing')),
                'sendPhoto' => $client->sendPhoto($peer, $this->path($parameters['photo']), $caption, [...$common, ...$this->parseMode($parameters)]),
                'sendVoice' => $client->sendVoice($peer, $this->path($parameters['voice']), $caption, $common),
                'sendAudio' => $client->sendAudio($peer, $this->path($parameters['audio']), $caption, $common),
                'sendVideo' => $client->sendVideo($peer, $this->path($parameters['video']), $caption, $common),
                'sendDocument' => $client->sendDocument($peer, $this->path($parameters['document']), $caption, [...$common, ...$this->parseMode($parameters)]),
                'sendMediaGroup' => $client->sendAlbum($peer, array_map(
                    fn ($item) => ['path' => $this->path($item['media']), 'type' => $item['type'] ?? 'photo', 'caption' => $item['caption'] ?? null],
                    $parameters['media']
                ), $common),
                'sendPoll' => $client->sendPoll($peer, (string) $parameters['question'], array_map(
                    fn ($option) => is_array($option) ? (string) $option['text'] : (string) $option,
                    $parameters['options']
                ), array_filter([
                    'multiple_choice' => $parameters['allows_multiple_answers'] ?? null,
                    'public_voters' => isset($parameters['is_anonymous']) ? ! $parameters['is_anonymous'] : null,
                    'correct_answers' => $parameters['correct_option_ids'] ?? (isset($parameters['correct_option_id']) ? [$parameters['correct_option_id']] : null),
                    'solution' => $parameters['explanation'] ?? null,
                    'close_period' => $parameters['open_period'] ?? null,
                    ...$common,
                ], fn ($value) => $value !== null)),
                'sendLocation' => $client->sendLocation($peer, (float) $parameters['latitude'], (float) $parameters['longitude'], $common),
                'sendVenue' => $client->sendVenue($peer, (float) $parameters['latitude'], (float) $parameters['longitude'], (string) $parameters['title'], (string) $parameters['address'], $common),
                'sendChecklist' => $client->sendChecklist($peer, (string) $parameters['checklist']['title'], array_map(
                    fn ($task) => (string) $task['text'], $parameters['checklist']['tasks']
                ), $common),
                'sendDice' => $client->sendDice($peer, (string) ($parameters['emoji'] ?? '🎲'), $common),
                'answerInlineQuery' => $client->answerInlineQuery((int) $parameters['inline_query_id'], $parameters['results'], array_filter([
                    'cache_time' => $parameters['cache_time'] ?? null,
                    'private' => $parameters['is_personal'] ?? null,
                ], fn ($value) => $value !== null)),
                'answerCallbackQuery' => $client->answerCallback((int) $parameters['callback_query_id'], (string) ($parameters['text'] ?? ''), (bool) ($parameters['show_alert'] ?? false)),
                'deleteMessage' => $client->deleteMessages(id: [(int) $parameters['message_id']], revoke: true),
                'setMessageReaction' => $client->sendReaction($peer, (int) $parameters['message_id'], array_map(
                    fn ($reaction) => $reaction['emoji'] ?? '', $parameters['reaction'] ?? []
                )),
            };
        } catch (TelegramException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TelegramException("MTProto rejected [{$method}]: ".$e->getMessage(), (int) $e->getCode(), $e);
        }

        return $this->normalizeResult($result);
    }

    /**
     * Determine if the transport supports the given Bot API method.
     */
    public function supports(string $method): bool
    {
        return in_array($method, static::METHODS, true);
    }

    /**
     * Translate the Bot API reply and keyboard parameters.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function commonParameters(array $parameters): array
    {
        return array_filter([
            'reply_to' => $parameters['reply_parameters']['message_id'] ?? null,
            'reply_markup' => $parameters['reply_markup'] ?? null,
            'silent' => $parameters['disable_notification'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Translate the Bot API parse mode.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, string>
     */
    protected function parseMode(array $parameters): array
    {
        $mode = $parameters['parse_mode'] ?? null;

        return $mode ? ['parse_mode' => strtolower($mode) === 'html' ? 'html' : 'markdown'] : [];
    }

    /**
     * Get a local path for a file given as an upload, path or URL.
     */
    protected function path(mixed $file): string
    {
        return $file instanceof Upload ? $file->toTemporaryFile() : (string) $file;
    }

    /**
     * Turn an MTProto result into a Bot API style result.
     */
    protected function normalizeResult(mixed $result): mixed
    {
        $data = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;

        if (! is_array($data)) {
            return $data ?? true;
        }

        $messageId = $this->findMessageId($data);

        return $messageId === null ? true : ['message_id' => $messageId];
    }

    /**
     * Find the id of the sent message in an updates result.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function findMessageId(array $data): ?int
    {
        $type = $data['_'] ?? null;

        if ($type === 'updateShortSentMessage' || $type === 'updateMessageID') {
            return (int) $data['id'];
        }

        if (in_array($type, ['updateNewMessage', 'updateNewChannelMessage', 'updateEditMessage', 'updateEditChannelMessage'], true)) {
            return (int) ($data['message']['id'] ?? 0) ?: null;
        }

        foreach ($data as $value) {
            if (is_array($value) && ($id = $this->findMessageId($value)) !== null) {
                return $id;
            }
        }

        return null;
    }
}
