<?php

namespace LaraGram\Ai;

use Closure;
use LaraGram\Support\Collection;
use InvalidArgumentException;
use LaraGram\Ai\Gateway\FakeRerankingGateway;
use LaraGram\Ai\PendingResponses\PendingReranking;

class Reranking
{
    /**
     * Create a new pending reranking for the given documents.
     *
     * @param  Collection<int, string>|array<int, string>  $documents
     *
     * @throws InvalidArgumentException if the given documents are not a list, are empty, are not strings, or contain only blank strings.
     */
    public static function of(Collection|array $documents): PendingReranking
    {
        if ($documents instanceof Collection) {
            $documents = $documents->values()->all();
        }

        return new PendingReranking($documents);
    }

    /**
     * Fake reranking operations.
     */
    public static function fake(Closure|array $responses = []): FakeRerankingGateway
    {
        return Ai::fakeReranking($responses);
    }

    /**
     * Determine if reranking is faked.
     */
    public static function isFaked(): bool
    {
        return Ai::rerankingIsFaked();
    }
}
