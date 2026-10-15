<?php

namespace LaraGram\Ai\Migrations;

use LaraGram\Database\Migrations\Migration;

abstract class AiMigration extends Migration
{
    /**
     * Get the migration connection name.
     */
    #[\Override]
    public function getConnection(): ?string
    {
        return config('ai.conversations.connection', config('database.default'));
    }
}
