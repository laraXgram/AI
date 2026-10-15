<?php

namespace LaraGram\Ai\Telegram\Formatting;

class Markdown
{
    /**
     * Parse Markdown into a list of block nodes.
     *
     * The parser covers the Markdown models write: headings, paragraphs, nested
     * lists and task lists, quotes, fenced code, tables, rules and math blocks.
     * Unterminated constructs, as found while a response is still streaming,
     * are kept as text instead of failing.
     *
     * @param  string  $markdown
     * @return array<int, array<string, mixed>>
     */
    public static function parse(string $markdown): array
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));

        return (new static)->blocks($lines);
    }

    /**
     * Parse the given lines into block nodes.
     *
     * @param  array<int, string>  $lines
     * @return array<int, array<string, mixed>>
     */
    protected function blocks(array $lines): array
    {
        $blocks = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;

                continue;
            }

            // Fenced code: ``` or ~~~, with an optional language.
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})\s*([\w#+.\-]*)/', $line, $m)) {
                $fence = $m[1];
                $code = [];
                $i++;

                while ($i < $count && ! preg_match('/^\s{0,3}'.preg_quote($fence[0], '/').'{'.strlen($fence).',}\s*$/', $lines[$i])) {
                    $code[] = $lines[$i++];
                }

                $i++;
                $blocks[] = ['type' => 'code', 'language' => $m[2] ?: null, 'text' => implode("\n", $code)];

                continue;
            }

            // Math block: $$ ... $$ or \[ ... \].
            if (preg_match('/^\s*(\$\$|\\\\\[)(.*)$/', $line, $m)) {
                $close = $m[1] === '$$' ? '$$' : '\]';
                $rest = $m[2];

                if (str_contains($rest, $close)) {
                    $blocks[] = ['type' => 'math', 'text' => trim(substr($rest, 0, strpos($rest, $close)))];
                    $i++;

                    continue;
                }

                $math = $rest === '' ? [] : [$rest];
                $i++;

                while ($i < $count && ! str_contains($lines[$i], $close)) {
                    $math[] = $lines[$i++];
                }

                if ($i < $count) {
                    $math[] = substr($lines[$i], 0, strpos($lines[$i], $close));
                    $i++;
                }

                $blocks[] = ['type' => 'math', 'text' => trim(implode("\n", $math))];

                continue;
            }

            // Heading.
            if (preg_match('/^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/', $line, $m)) {
                $blocks[] = ['type' => 'heading', 'level' => strlen($m[1]), 'inlines' => Inline::parse($m[2])];
                $i++;

                continue;
            }

            // Horizontal rule.
            if (preg_match('/^\s{0,3}([-*_])(\s*\1){2,}\s*$/', $line)) {
                $blocks[] = ['type' => 'rule'];
                $i++;

                continue;
            }

            // Table: a row followed by a delimiter row.
            if (str_contains($line, '|') && isset($lines[$i + 1]) && preg_match('/^\s*\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)*\|?\s*$/', $lines[$i + 1])) {
                $header = $this->cells($line);
                $align = array_map(function ($cell) {
                    $cell = trim($cell);

                    return match (true) {
                        str_starts_with($cell, ':') && str_ends_with($cell, ':') => 'center',
                        str_ends_with($cell, ':') => 'right',
                        str_starts_with($cell, ':') => 'left',
                        default => null,
                    };
                }, $this->cells($lines[$i + 1]));

                $rows = [];
                $i += 2;

                while ($i < $count && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
                    $rows[] = array_map(Inline::parse(...), $this->cells($lines[$i++]));
                }

                $blocks[] = [
                    'type' => 'table',
                    'header' => array_map(Inline::parse(...), $header),
                    'align' => $align,
                    'rows' => $rows,
                ];

                continue;
            }

            // Block quote.
            if (preg_match('/^\s{0,3}>/', $line)) {
                $quoted = [];

                while ($i < $count && preg_match('/^\s{0,3}>\s?(.*)$/', $lines[$i], $m)) {
                    $quoted[] = $m[1];
                    $i++;
                }

                $blocks[] = ['type' => 'quote', 'blocks' => $this->blocks($quoted)];

                continue;
            }

            // List.
            if (preg_match('/^(\s*)([-*+]|\d{1,9}[.)])\s+/', $line, $m)) {
                [$block, $i] = $this->list($lines, $i, strlen($m[1]));
                $blocks[] = $block;

                continue;
            }

            // Paragraph: until a blank line or the start of another block.
            $paragraph = [];

            while ($i < $count && trim($lines[$i]) !== '' && ! $this->startsBlock($lines, $i, $paragraph === [])) {
                $paragraph[] = $lines[$i++];
            }

            if ($paragraph === []) {
                $paragraph[] = $lines[$i++];
            }

            $blocks[] = ['type' => 'paragraph', 'inlines' => Inline::parse(implode("\n", array_map('rtrim', $paragraph)))];
        }

        return $blocks;
    }

    /**
     * Parse a list starting at the given line.
     *
     * @param  array<int, string>  $lines
     * @param  int  $i
     * @param  int  $indent
     * @return array{0: array<string, mixed>, 1: int}
     */
    protected function list(array $lines, int $i, int $indent): array
    {
        preg_match('/^\s*([-*+]|(\d{1,9})[.)])\s+/', $lines[$i], $m);

        $ordered = isset($m[2]) && $m[2] !== '';
        $start = $ordered ? (int) $m[2] : 1;
        $items = [];
        $count = count($lines);

        while ($i < $count) {
            if (! preg_match('/^(\s*)([-*+]|\d{1,9}[.)])\s+(.*)$/', $lines[$i], $m) || strlen($m[1]) !== $indent) {
                break;
            }

            if (ctype_digit($m[2][0]) !== $ordered) {
                break;
            }

            $content = [$m[3]];
            $contentIndent = strlen($m[1]) + strlen($m[2]) + 1;
            $i++;

            while ($i < $count) {
                $line = $lines[$i];

                if (trim($line) === '') {
                    $next = $lines[$i + 1] ?? '';

                    if (strlen($next) - strlen(ltrim($next)) > $indent && trim($next) !== '') {
                        $content[] = '';
                        $i++;

                        continue;
                    }

                    break;
                }

                $lineIndent = strlen($line) - strlen(ltrim($line));

                if ($lineIndent <= $indent && preg_match('/^\s*([-*+]|\d{1,9}[.)])\s+/', $line)) {
                    break;
                }

                if ($lineIndent <= $indent && $this->startsBlock($lines, $i, true)) {
                    break;
                }

                $content[] = substr($line, min($lineIndent, $contentIndent));
                $i++;
            }

            $checked = null;

            if (preg_match('/^\[([ xX])\]\s+(.*)$/s', $content[0], $task)) {
                $checked = strtolower($task[1]) === 'x';
                $content[0] = $task[2];
            }

            $items[] = ['checked' => $checked, 'blocks' => $this->blocks($content)];

            if ($i < $count && trim($lines[$i]) === '') {
                $next = $lines[$i + 1] ?? '';

                if (preg_match('/^(\s*)([-*+]|\d{1,9}[.)])\s+/', $next, $n) && strlen($n[1]) === $indent && ctype_digit($n[2][0]) === $ordered) {
                    $i++;
                }
            }
        }

        return [['type' => 'list', 'ordered' => $ordered, 'start' => $start, 'items' => $items], $i];
    }

    /**
     * Determine if the line at the given index starts a block other than a paragraph.
     *
     * @param  array<int, string>  $lines
     * @param  int  $i
     * @param  bool  $first
     * @return bool
     */
    protected function startsBlock(array $lines, int $i, bool $first): bool
    {
        $line = $lines[$i];

        return preg_match('/^\s{0,3}(`{3,}|~{3,}|#{1,6}\s|>|\$\$|\\\\\[)/', $line)
            || preg_match('/^\s{0,3}([-*_])(\s*\1){2,}\s*$/', $line)
            || (! $first && preg_match('/^\s*([-*+]|\d{1,9}[.)])\s+/', $line))
            || (str_contains($line, '|') && isset($lines[$i + 1]) && preg_match('/^\s*\|?\s*:?-{1,}:?\s*(\|\s*:?-{1,}:?\s*)*\|?\s*$/', $lines[$i + 1]));
    }

    /**
     * Split a table row into its cells.
     *
     * @param  string  $row
     * @return array<int, string>
     */
    protected function cells(string $row): array
    {
        $row = trim($row);
        $row = preg_replace('/^\||\|$/', '', $row);

        $cells = preg_split('/(?<!\\\\)\|/', $row);

        return array_map(fn ($cell) => str_replace('\|', '|', trim($cell)), $cells);
    }
}
