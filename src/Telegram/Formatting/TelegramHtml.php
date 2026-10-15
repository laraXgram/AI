<?php

namespace LaraGram\Ai\Telegram\Formatting;

class TelegramHtml
{
    /**
     * The longest text Telegram accepts in one message, in UTF-16 code units.
     */
    public const MESSAGE_LIMIT = 4096;

    /**
     * Render Markdown as Telegram HTML, split into messages Telegram accepts.
     *
     * @param  string  $markdown
     * @param  int  $limit
     * @return array<int, string>
     */
    public static function chunks(string $markdown, int $limit = self::MESSAGE_LIMIT): array
    {
        $renderer = new static;

        $pieces = [];

        foreach (Markdown::parse($markdown) as $block) {
            foreach ($renderer->splitBlock($block, $limit) as $piece) {
                $pieces[] = $piece;
            }
        }

        $chunks = [];
        $current = '';

        foreach ($pieces as $piece) {
            $candidate = $current === '' ? $piece : $current."\n\n".$piece;

            if ($current !== '' && static::length($candidate) > $limit) {
                $chunks[] = $current;
                $current = $piece;
            } else {
                $current = $candidate;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Render Markdown as a single Telegram HTML string, without splitting it.
     *
     * @param  string  $markdown
     * @return string
     */
    public static function render(string $markdown): string
    {
        $renderer = new static;

        return implode("\n\n", array_map($renderer->block(...), Markdown::parse($markdown)));
    }

    /**
     * Get the visible length of Telegram HTML in UTF-16 code units.
     *
     * @param  string  $html
     * @return int
     */
    public static function length(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * Render a block, split into pieces that each fit in a message.
     *
     * @param  array<string, mixed>  $block
     * @param  int  $limit
     * @return array<int, string>
     */
    protected function splitBlock(array $block, int $limit): array
    {
        $html = $this->block($block);

        if (static::length($html) <= $limit) {
            return [$html];
        }

        // Split long code blocks line by line, keeping the <pre> wrapper on every piece.
        if ($block['type'] === 'code' || $block['type'] === 'table') {
            $text = $block['type'] === 'code' ? $block['text'] : $this->tableText($block);

            return array_map(
                fn ($part) => $this->pre($part, $block['language'] ?? null),
                $this->splitText($text, $limit - 20)
            );
        }

        if ($block['type'] === 'list' || $block['type'] === 'quote') {
            $children = $block['type'] === 'list'
                ? $this->listLines($block, 0)
                : array_map($this->block(...), $block['blocks']);

            $pieces = [];

            foreach ($children as $child) {
                foreach (static::length($child) > $limit ? $this->splitPlain($child, $limit) : [$child] as $part) {
                    $pieces[] = $block['type'] === 'quote' ? '<blockquote>'.$part.'</blockquote>' : $part;
                }
            }

            return $this->join($pieces, $limit, $block['type'] === 'list' ? "\n" : "\n\n");
        }

        return $this->splitPlain($html, $limit);
    }

    /**
     * Join pieces greedily into strings that fit the limit.
     *
     * @param  array<int, string>  $pieces
     * @param  int  $limit
     * @param  string  $glue
     * @return array<int, string>
     */
    protected function join(array $pieces, int $limit, string $glue): array
    {
        $chunks = [];
        $current = '';

        foreach ($pieces as $piece) {
            $candidate = $current === '' ? $piece : $current.$glue.$piece;

            if ($current !== '' && static::length($candidate) > $limit) {
                $chunks[] = $current;
                $current = $piece;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Split rendered HTML that is too long by dropping its markup.
     *
     * @param  string  $html
     * @param  int  $limit
     * @return array<int, string>
     */
    protected function splitPlain(string $html, int $limit): array
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_map(static::escape(...), $this->splitText($text, $limit));
    }

    /**
     * Split plain text at line, then word, boundaries.
     *
     * @param  string  $text
     * @param  int  $limit
     * @return array<int, string>
     */
    protected function splitText(string $text, int $limit): array
    {
        $parts = [];
        $current = '';

        foreach (preg_split('/(?<=\n)|(?<= )/u', $text) as $word) {
            while (mb_strlen($word) > $limit) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }

                $parts[] = mb_substr($word, 0, $limit);
                $word = mb_substr($word, $limit);
            }

            if (static::length(static::escape($current.$word)) > $limit) {
                $parts[] = $current;
                $current = $word;
            } else {
                $current .= $word;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return array_values(array_filter(array_map('rtrim', $parts), fn ($part) => $part !== ''));
    }

    /**
     * Render a block node.
     *
     * @param  array<string, mixed>  $block
     * @return string
     */
    public function block(array $block): string
    {
        return match ($block['type']) {
            'heading' => '<b>'.str_replace(['<b>', '</b>'], '', $this->inlines($block['inlines'])).'</b>',
            'paragraph' => $this->inlines($block['inlines']),
            'code' => $this->pre($block['text'], $block['language']),
            'math' => $this->pre($block['text'], null),
            'quote' => '<blockquote>'.implode("\n\n", array_map(
                fn ($child) => $child['type'] === 'quote' ? strip_tags($this->block($child), '<b><i><u><s><code><a><tg-spoiler>') : $this->block($child),
                $block['blocks']
            )).'</blockquote>',
            'list' => implode("\n", $this->listLines($block, 0)),
            'table' => $this->pre($this->tableText($block), null),
            'rule' => '──────────',
            default => '',
        };
    }

    /**
     * Render a list as lines with bullets or numbers.
     *
     * @param  array<string, mixed>  $list
     * @param  int  $depth
     * @return array<int, string>
     */
    protected function listLines(array $list, int $depth): array
    {
        $lines = [];
        $indent = str_repeat('    ', $depth);
        $number = $list['start'];

        foreach ($list['items'] as $item) {
            $marker = match (true) {
                $item['checked'] === true => '☑',
                $item['checked'] === false => '☐',
                $list['ordered'] => ($number++).'.',
                default => $depth % 2 === 0 ? '•' : '◦',
            };

            $first = true;

            foreach ($item['blocks'] as $child) {
                if ($child['type'] === 'list') {
                    array_push($lines, ...$this->listLines($child, $depth + 1));

                    continue;
                }

                $rendered = $this->block($child);

                $lines[] = $first
                    ? $indent.$marker.' '.$rendered
                    : $indent.'   '.$rendered;

                $first = false;
            }

            if ($first) {
                $lines[] = $indent.$marker;
            }
        }

        return $lines;
    }

    /**
     * Lay a table out as aligned monospaced text.
     *
     * @param  array<string, mixed>  $table
     * @return string
     */
    protected function tableText(array $table): string
    {
        $rows = [array_map(Inline::plain(...), $table['header'])];

        foreach ($table['rows'] as $row) {
            $rows[] = array_map(Inline::plain(...), $row);
        }

        $columns = max(array_map('count', $rows));
        $widths = array_fill(0, $columns, 0);

        foreach ($rows as $row) {
            foreach (array_pad($row, $columns, '') as $index => $cell) {
                $widths[$index] = max($widths[$index], mb_strwidth($cell));
            }
        }

        $lines = [];

        foreach ($rows as $number => $row) {
            $cells = [];

            foreach (array_pad($row, $columns, '') as $index => $cell) {
                $padding = $widths[$index] - mb_strwidth($cell);

                $cells[] = match ($table['align'][$index] ?? null) {
                    'right' => str_repeat(' ', $padding).$cell,
                    'center' => str_repeat(' ', intdiv($padding, 2)).$cell.str_repeat(' ', $padding - intdiv($padding, 2)),
                    default => $cell.str_repeat(' ', $padding),
                };
            }

            $lines[] = rtrim(implode(' │ ', $cells));

            if ($number === 0) {
                $lines[] = implode('─┼─', array_map(fn ($width) => str_repeat('─', $width), $widths));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render a preformatted block.
     *
     * @param  string  $text
     * @param  string|null  $language
     * @return string
     */
    protected function pre(string $text, ?string $language): string
    {
        return $language
            ? '<pre><code class="language-'.static::escape($language).'">'.static::escape($text).'</code></pre>'
            : '<pre>'.static::escape($text).'</pre>';
    }

    /**
     * Render inline nodes.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return string
     */
    public function inlines(array $nodes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= match ($node['type']) {
                'text' => static::escape($node['text']),
                'break' => "\n",
                'code', 'math' => '<code>'.static::escape($node['text']).'</code>',
                'strong' => '<b>'.$this->inlines($node['children']).'</b>',
                'em' => '<i>'.$this->inlines($node['children']).'</i>',
                'del' => '<s>'.$this->inlines($node['children']).'</s>',
                'spoiler' => '<tg-spoiler>'.$this->inlines($node['children']).'</tg-spoiler>',
                'link' => $this->link($node),
                default => '',
            };
        }

        return $html;
    }

    /**
     * Render a link, dropping URLs Telegram would reject.
     *
     * @param  array<string, mixed>  $node
     * @return string
     */
    protected function link(array $node): string
    {
        $text = $this->inlines($node['children']);

        if (! preg_match('/^(https?|tg|mailto):/i', $node['url'])) {
            return $text;
        }

        return '<a href="'.static::escape($node['url']).'">'.$text.'</a>';
    }

    /**
     * Escape text for Telegram HTML.
     *
     * @param  string  $text
     * @return string
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }
}
