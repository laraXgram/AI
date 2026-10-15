<?php

namespace LaraGram\Ai\Telegram\Formatting;

use LaraGram\Template\Rich\RichMessage;

class RichMarkup
{
    /**
     * Render Markdown as a rich message payload.
     *
     * @param  string  $markdown
     * @param  bool  $draft
     * @param  string|null  $thinking
     * @return array<string, mixed>
     */
    public static function message(string $markdown, bool $draft = false, ?string $thinking = null): array
    {
        $markup = static::render($markdown);

        if ($draft && $thinking !== null) {
            $markup = '<tg-thinking>'.RichMessage::escape($thinking).'</tg-thinking>'.$markup;
        }

        return RichMessage::make()->draft($draft)->append($markup)->toArray();
    }

    /**
     * Render Markdown as rich message markup.
     *
     * @param  string  $markdown
     * @return string
     */
    public static function render(string $markdown): string
    {
        $renderer = new static;

        return implode('', array_map($renderer->block(...), Markdown::parse($markdown)));
    }

    /**
     * Determine if the Markdown uses structure a plain message cannot show.
     *
     * @param  string  $markdown
     * @return bool
     */
    public static function needsRichMessage(string $markdown): bool
    {
        foreach (Markdown::parse($markdown) as $block) {
            if (in_array($block['type'], ['table', 'heading', 'math'], true)) {
                return true;
            }
        }

        return false;
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
            'heading' => '<h'.$block['level'].'>'.$this->inlines($block['inlines']).'</h'.$block['level'].'>',
            'paragraph' => '<p>'.$this->inlines($block['inlines']).'</p>',
            'code' => '<pre>'.($block['language'] ? '<code class="language-'.RichMessage::escape($block['language']).'">' : '<code>')
                .RichMessage::escape($block['text']).'</code></pre>',
            'math' => '<tg-math-block>'.RichMessage::escape($block['text']).'</tg-math-block>',
            'quote' => '<blockquote>'.implode('', array_map($this->block(...), $block['blocks'])).'</blockquote>',
            'list' => $this->list($block),
            'table' => $this->table($block),
            'rule' => '<hr>',
            default => '',
        };
    }

    /**
     * Render a list.
     *
     * @param  array<string, mixed>  $list
     * @return string
     */
    protected function list(array $list): string
    {
        $tag = $list['ordered'] ? 'ol' : 'ul';
        $html = '<'.$tag.($list['ordered'] && $list['start'] !== 1 ? ' start="'.$list['start'].'"' : '').'>';

        foreach ($list['items'] as $item) {
            $html .= '<li>';

            if ($item['checked'] !== null) {
                $html .= '<input type="checkbox"'.($item['checked'] ? ' checked' : '').'>';
            }

            // A single paragraph is written inline, as in a tight Markdown list.
            if (count($item['blocks']) === 1 && $item['blocks'][0]['type'] === 'paragraph') {
                $html .= $this->inlines($item['blocks'][0]['inlines']);
            } else {
                foreach ($item['blocks'] as $index => $child) {
                    $html .= $index === 0 && $child['type'] === 'paragraph'
                        ? $this->inlines($child['inlines'])
                        : $this->block($child);
                }
            }

            $html .= '</li>';
        }

        return $html.'</'.$tag.'>';
    }

    /**
     * Render a table.
     *
     * @param  array<string, mixed>  $table
     * @return string
     */
    protected function table(array $table): string
    {
        $align = fn (int $column) => isset($table['align'][$column]) && $table['align'][$column] !== null
            ? ' align="'.$table['align'][$column].'"'
            : '';

        $html = '<table bordered><tr>';

        foreach ($table['header'] as $column => $cell) {
            $html .= '<th'.$align($column).'>'.$this->inlines($cell).'</th>';
        }

        $html .= '</tr>';

        foreach ($table['rows'] as $row) {
            $html .= '<tr>';

            foreach ($row as $column => $cell) {
                $html .= '<td'.$align($column).'>'.$this->inlines($cell).'</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</table>';
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
                'text' => RichMessage::escape($node['text']),
                'break' => '<br>',
                'code' => '<code>'.RichMessage::escape($node['text']).'</code>',
                'math' => '<tg-math>'.RichMessage::escape($node['text']).'</tg-math>',
                'strong' => '<b>'.$this->inlines($node['children']).'</b>',
                'em' => '<i>'.$this->inlines($node['children']).'</i>',
                'del' => '<s>'.$this->inlines($node['children']).'</s>',
                'spoiler' => '<tg-spoiler>'.$this->inlines($node['children']).'</tg-spoiler>',
                'link' => preg_match('/^(https?|tg|mailto):/i', $node['url'])
                    ? '<a href="'.RichMessage::escape($node['url']).'">'.$this->inlines($node['children']).'</a>'
                    : $this->inlines($node['children']),
                default => '',
            };
        }

        return $html;
    }
}
