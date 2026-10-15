<?php

namespace LaraGram\Ai\Telegram\Messengers;

use CURLStringFile;
use LaraGram\Ai\Telegram\TelegramException;
use LaraGram\Ai\Telegram\Upload;
use LaraGram\Laraquest\Response;
use LaraGram\Request\Request;

class BotApiMessenger extends Messenger
{
    /**
     * Call a Bot API method through the chat's bot connection.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws \LaraGram\Ai\Telegram\TelegramException
     */
    public function call(string $method, array $parameters = []): mixed
    {
        $request = new Request;

        if ($this->chat->connection !== null) {
            $request->useConnection($this->chat->connection);
        }

        $response = $request->call($method, $this->prepareFiles($parameters));

        if ($response instanceof Response) {
            if ($response->failed()) {
                throw TelegramException::failed($method, $response->description(), $response->errorCode());
            }

            $result = $response->result(true);

            return $result ?? true;
        }

        if (is_array($response) && ($response['ok'] ?? true) === false) {
            throw TelegramException::failed($method, $response['description'] ?? null, $response['error_code'] ?? null);
        }

        return is_array($response) ? ($response['result'] ?? $response) : $response;
    }

    /**
     * Determine if the transport supports the given Bot API method.
     */
    public function supports(string $method): bool
    {
        return true;
    }

    /**
     * Turn uploads into multipart files, attaching the ones nested in media arrays.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function prepareFiles(array $parameters): array
    {
        $attachments = [];

        $convert = function (mixed $value) use (&$convert, &$attachments): mixed {
            if ($value instanceof Upload) {
                $name = 'file'.count($attachments);
                $attachments[$name] = new CURLStringFile($value->contents, $value->filename, $value->mimeType ?? 'application/octet-stream');

                return 'attach://'.$name;
            }

            return is_array($value) ? array_map($convert, $value) : $value;
        };

        foreach ($parameters as $key => $value) {
            if ($value instanceof Upload) {
                $parameters[$key] = new CURLStringFile($value->contents, $value->filename, $value->mimeType ?? 'application/octet-stream');
            } elseif (is_array($value)) {
                $parameters[$key] = $convert($value);
            }
        }

        return [...$parameters, ...$attachments];
    }
}
