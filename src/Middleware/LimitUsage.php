<?php

namespace LaraGram\Ai\Middleware;

use Closure;
use LaraGram\Ai\Gateway\StepResponse;
use LaraGram\Ai\PendingStep;
use LaraGram\Ai\Responses\Data\FinishReason;
use LaraGram\Ai\Responses\Data\Meta;
use LaraGram\Ai\Responses\Data\TextUsage;
use LaraGram\Ai\Telegram\Chat;
use LaraGram\Ai\Telegram\Sender;
use LaraGram\Contracts\Cache\Repository;
use LaraGram\Support\Facades\Cache;
use LaraGram\Support\Tempora;

class LimitUsage
{
    /**
     * The payload prefix of the invoices that buy more tokens.
     */
    public const PAYLOAD_PREFIX = 'laragram-ai:tokens:';

    /**
     * The message sent when the user has no tokens left.
     */
    protected string $message = 'You have reached your usage limit. Please try again later.';

    /**
     * The Stars price and token amount of a top-up, if top-ups are offered.
     *
     * @var array{stars: int, tokens: int, title: string, description: string}|null
     */
    protected ?array $topUp = null;

    /**
     * Create a new middleware instance.
     *
     * @param  int  $tokens  The tokens each user may spend per period.
     * @param  string  $period  "hour", "day", "week" or "month".
     */
    public function __construct(
        protected int $tokens,
        protected string $period = 'day',
    ) {}

    /**
     * Limit each Telegram user to the given number of tokens per period.
     */
    public static function perUser(int $tokens, string $period = 'day'): static
    {
        return new static($tokens, $period);
    }

    /**
     * Set the message sent when the user has no tokens left.
     *
     * @return $this
     */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Offer more tokens for Telegram Stars when the user runs out.
     *
     * @return $this
     */
    public function topUpWithStars(int $stars, int $tokens, string $title = 'More tokens', ?string $description = null): static
    {
        $this->topUp = [
            'stars' => $stars,
            'tokens' => $tokens,
            'title' => $title,
            'description' => $description ?? number_format($tokens).' more tokens for the assistant.',
        ];

        return $this;
    }

    /**
     * Stop the step when the user has no tokens left, and record what the step spends.
     */
    public function handle(PendingStep $step, Closure $next): mixed
    {
        $chat = Chat::current();
        $user = $chat?->userId;

        if ($user === null) {
            return $next($step);
        }

        if ($this->remaining($user) <= 0) {
            if ($step->isFirstStep()) {
                $this->sendLimitReached($chat);
            }

            return new StepResponse('', [], FinishReason::Stop, new TextUsage, new Meta($step->provider, $step->model));
        }

        return $next($step)->then(function (StepResponse $response) use ($user) {
            $this->spend($user, $response->usage->inputTokens + $response->usage->outputTokens);
        });
    }

    /**
     * Get the tokens the user has left, including purchased tokens.
     */
    public function remaining(int|string $user): int
    {
        return max(0, $this->tokens - (int) $this->store()->get($this->usageKey($user), 0))
            + (int) $this->store()->get(static::creditsKey($user), 0);
    }

    /**
     * Record the tokens the user spent.
     */
    public function spend(int|string $user, int $tokens): void
    {
        $used = (int) $this->store()->get($this->usageKey($user), 0);
        $allowance = max(0, $this->tokens - $used);

        $this->store()->put($this->usageKey($user), $used + min($tokens, $allowance), $this->periodEnd());

        if (($overflow = $tokens - $allowance) > 0) {
            $credits = (int) $this->store()->get(static::creditsKey($user), 0);

            $this->store()->forever(static::creditsKey($user), max(0, $credits - $overflow));
        }
    }

    /**
     * Give the user purchased tokens.
     */
    public static function credit(int|string $user, int $tokens): void
    {
        $store = static::cache();

        $store->forever(static::creditsKey($user), (int) $store->get(static::creditsKey($user), 0) + $tokens);
    }

    /**
     * Tell the user they are out of tokens, offering a top-up when one is configured.
     */
    protected function sendLimitReached(Chat $chat): void
    {
        $sender = Sender::to($chat);

        $sender->markdown($this->message);

        if ($this->topUp === null || $chat->usesMtproto()) {
            return;
        }

        $sender->call('sendInvoice', [
            'chat_id' => $chat->id,
            'title' => mb_substr($this->topUp['title'], 0, 32),
            'description' => mb_substr($this->topUp['description'], 0, 255),
            'payload' => static::PAYLOAD_PREFIX.$this->topUp['tokens'].':'.$chat->userId,
            'currency' => 'XTR',
            'prices' => [['label' => $this->topUp['title'], 'amount' => $this->topUp['stars']]],
        ]);
    }

    /**
     * Get the cache key of the user's usage in the current period.
     */
    protected function usageKey(int|string $user): string
    {
        $start = match ($this->period) {
            'hour' => Tempora::now()->format('YmdH'),
            'week' => Tempora::now()->format('oW'),
            'month' => Tempora::now()->format('Ym'),
            default => Tempora::now()->format('Ymd'),
        };

        return 'laragram-ai:usage:'.$this->period.':'.$start.':'.$user;
    }

    /**
     * Get the cache key of the user's purchased tokens.
     */
    protected static function creditsKey(int|string $user): string
    {
        return 'laragram-ai:credits:'.$user;
    }

    /**
     * Get the end of the current period.
     */
    protected function periodEnd(): \DateTimeInterface
    {
        return match ($this->period) {
            'hour' => Tempora::now()->endOfHour(),
            'week' => Tempora::now()->endOfWeek(),
            'month' => Tempora::now()->endOfMonth(),
            default => Tempora::now()->endOfDay(),
        };
    }

    /**
     * Get the cache store that holds the usage.
     */
    protected function store(): Repository
    {
        return static::cache();
    }

    /**
     * Get the configured cache store.
     */
    protected static function cache(): Repository
    {
        return Cache::store(config('ai.telegram.usage.store'));
    }
}
