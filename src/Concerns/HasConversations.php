<?php

namespace LaraGram\Ai\Concerns;

use LaraGram\Database\Eloquent\Relations\MorphMany;
use LaraGram\Ai\Models\Conversation;

trait HasConversations
{
    /**
     * Get the conversations for the model.
     *
     * @return MorphMany<Conversation, $this>
     */
    public function conversations(): MorphMany
    {
        return $this->morphMany(Conversation::class, 'participant');
    }
}
