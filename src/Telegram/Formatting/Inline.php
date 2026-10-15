<?php

namespace LaraGram\Ai\Telegram\Formatting;

class Inline
{
    /**
     * The emphasis delimiters, longest first, with the node each one produces.
     *
     * @var array<string, string>
     */
    protected const DELIMITERS = [
        '***' => 'strong-em',
        '___' => 'strong-em',
        '**' => 'strong',
        '__' => 'strong',
        '~~' => 'del',
        '||' => 'spoiler',
        '*' => 'em',
        '_' => 'em',
    ];

    /**
     * Parse inline Markdown into a list of inline nodes.
     *
     * @param  string  $text
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $text): array
    {
        return (new static)->nodes($text);
    }

    /**
     * Get the plain text of the given inline nodes.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return string
     */
    public static function plain(array $nodes): string
    {
        $text = '';

        foreach ($nodes as $node) {
            $text .= match ($node['type']) {
                'text', 'code', 'math' => $node['text'],
                'break' => "\n",
                default => static::plain($node['children'] ?? []),
            };
        }

        return $text;
    }

    /**
     * Parse the given text into nodes.
     *
     * @param  string  $text
     * @return array<int, array<string, mixed>>
     */
    protected function nodes(string $text): array
    {
        $nodes = [];
        $buffer = '';
        $length = strlen($text);
        $i = 0;

        $flush = function () use (&$buffer, &$nodes) {
            if ($buffer !== '') {
                $nodes[] = ['type' => 'text', 'text' => $buffer];
                $buffer = '';
            }
        };

        while ($i < $length) {
            $char = $text[$i];

            // Backslash escapes.
            if ($char === '\\' && $i + 1 < $length) {
                if ($text[$i + 1] === '(' && ($end = strpos($text, '\\)', $i + 2)) !== false) {
                    $flush();
                    $nodes[] = ['type' => 'math', 'text' => trim(substr($text, $i + 2, $end - $i - 2))];
                    $i = $end + 2;

                    continue;
                }

                if (ctype_punct($text[$i + 1])) {
                    $buffer .= $text[$i + 1];
                    $i += 2;

                    continue;
                }
            }

            // Hard line break: two trailing spaces or a backslash before a newline.
            if ($char === "\n") {
                $flush();
                $nodes[] = ['type' => 'break'];
                $i++;

                continue;
            }

            // Code span.
            if ($char === '`' && preg_match('/\G(`+)(.+?)(?<!`)\1(?!`)/s', $text, $m, 0, $i)) {
                $flush();
                $code = $m[2];

                if (strlen($code) > 2 && $code[0] === ' ' && str_ends_with($code, ' ') && trim($code) !== '') {
                    $code = substr($code, 1, -1);
                }

                $nodes[] = ['type' => 'code', 'text' => $code];
                $i += strlen($m[0]);

                continue;
            }

            // Image: ![alt](url), sent as a link.
            if ($char === '!' && preg_match('/\G!\[([^\]]*)\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/', $text, $m, 0, $i)) {
                $flush();
                $nodes[] = ['type' => 'link', 'url' => $m[2], 'children' => [['type' => 'text', 'text' => $m[1] !== '' ? $m[1] : $m[2]]]];
                $i += strlen($m[0]);

                continue;
            }

            // Link: [text](url).
            if ($char === '[' && preg_match('/\G\[((?:[^\[\]]|\[[^\]]*\])*)\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/', $text, $m, 0, $i)) {
                $flush();
                $nodes[] = ['type' => 'link', 'url' => $m[2], 'children' => $this->nodes($m[1])];
                $i += strlen($m[0]);

                continue;
            }

            // Autolink: <https://...>.
            if ($char === '<' && preg_match('/\G<((?:https?|tg|mailto):[^>\s]+)>/', $text, $m, 0, $i)) {
                $flush();
                $nodes[] = ['type' => 'link', 'url' => $m[1], 'children' => [['type' => 'text', 'text' => $m[1]]]];
                $i += strlen($m[0]);

                continue;
            }

            // Emphasis, strike-through and spoilers.
            foreach (static::DELIMITERS as $delimiter => $type) {
                if (substr($text, $i, strlen($delimiter)) !== $delimiter) {
                    continue;
                }

                if (($match = $this->closing($text, $i, $delimiter)) === null) {
                    continue;
                }

                $flush();

                $children = $this->nodes(substr($text, $i + strlen($delimiter), $match - $i - strlen($delimiter)));

                $nodes[] = $type === 'strong-em'
                    ? ['type' => 'strong', 'children' => [['type' => 'em', 'children' => $children]]]
                    : ['type' => $type, 'children' => $children];

                $i = $match + strlen($delimiter);

                continue 2;
            }

            $buffer .= $char;
            $i++;
        }

        $flush();

        return $nodes;
    }

    /**
     * Find the position of the delimiter closing the one at the given position.
     *
     * @param  string  $text
     * @param  int  $start
     * @param  string  $delimiter
     * @return int|null
     */
    protected function closing(string $text, int $start, string $delimiter): ?int
    {
        $open = $start + strlen($delimiter);

        // An opening delimiter must be followed by a non-space character.
        if (! isset($text[$open]) || ctype_space($text[$open])) {
            return null;
        }

        // Underscores inside words ("snake_case") are not emphasis.
        if ($delimiter[0] === '_' && $start > 0 && ctype_alnum($text[$start - 1])) {
            return null;
        }

        $offset = $open;

        while (($position = strpos($text, $delimiter, $offset)) !== false) {
            $skip = false;

            // Skip delimiters inside code spans.
            $before = substr($text, $open, $position - $open);

            if (substr_count($before, '`') % 2 === 1) {
                $skip = true;
            }

            // A closing delimiter must follow a non-space character, and a single
            // "*" must not be part of a "**" run.
            if (! $skip && (ctype_space($text[$position - 1]) || $position === $open)) {
                $skip = true;
            }

            if (! $skip && strlen($delimiter) === 1 && (($text[$position + 1] ?? '') === $delimiter || $text[$position - 1] === $delimiter)) {
                $skip = true;
            }

            if (! $skip && $delimiter[0] === '_' && ctype_alnum($text[$position + strlen($delimiter)] ?? ' ')) {
                $skip = true;
            }

            if (! $skip) {
                return $position;
            }

            $offset = $position + strlen($delimiter);
        }

        return null;
    }
}
