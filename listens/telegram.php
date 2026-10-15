<?php

use LaraGram\Ai\Telegram\Approvals;
use LaraGram\Ai\Telegram\StreamDelivery;
use LaraGram\Request\Request;
use LaraGram\Support\Facades\Bot;

/*
|--------------------------------------------------------------------------
| AI SDK Telegram Listens
|--------------------------------------------------------------------------
|
| These listens power the Telegram side of the AI SDK: the buttons that
| approve or reject a tool call, and the stop button of a streamed draft.
| They may be disabled with the "ai.telegram.listens" option.
|
*/

Bot::onCallbackQueryData(Approvals::PREFIX.':{token}:{call}:{decision}', function (Request $request, string $token, string $call, string $decision) {
    Approvals::decide($request, $token, $call, $decision);
})->where(['token' => '[A-Za-z0-9]+', 'call' => '[0-9]+|\*', 'decision' => '[ar]']);

Bot::onStoppedMessageGeneration(function (Request $request) {
    $stopped = $request->stopped_message_generation;

    StreamDelivery::stop($stopped->chat->id, $stopped->draft_id);
});
