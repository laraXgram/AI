<?php

namespace LaraGram\Ai\Models;

use LaraGram\Database\Eloquent\Attributes\WithoutIncrementing;
use LaraGram\Database\Eloquent\Model;
use LaraGram\Database\Eloquent\Relations\HasMany;
use LaraGram\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

#[WithoutIncrementing]
class Conversation extends Model
{
    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the messages for the conversation.
     *
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }

    /**
     * Get the participant that owns the conversation.
     *
     * @return MorphTo<Model, $this>
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the table associated with the model.
     */
    #[\Override]
    public function getTable(): string
    {
        return config('ai.conversations.tables.conversations', 'agent_conversations');
    }

    /**
     * Get the database connection for the model.
     */
    #[\Override]
    public function getConnectionName(): ?string
    {
        return config('ai.conversations.connection');
    }

    /**
     * Resolve the participant_type discriminator to record for the participant.
     */
    public static function participantType(object $participant): string
    {
        if ($participant instanceof Model) {
            return $participant->getMorphClass();
        }

        // Telegram users and chats from an update, such as user() and chat()...
        if ($participant instanceof \stdClass || str_starts_with($participant::class, 'LaraGram\\Laraquest\\')) {
            return isset($participant->type) && in_array($participant->type, ['private', 'group', 'supergroup', 'channel'], true)
                ? 'telegram:chat'
                : 'telegram:user';
        }

        return $participant::class;
    }

    /**
     * Resolve the participant_id key to record for the participant.
     */
    public static function participantKey(object $participant): string|int
    {
        if ($participant instanceof Model) {
            return $participant->getKey();
        }

        return $participant->id ?? throw new InvalidArgumentException(
            'The conversation participant must be an Eloquent model or expose an [id] property.'
        );
    }
}
