<?php

declare(strict_types=1);

namespace Polski\AIFeed;

defined('ABSPATH') || exit;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts post HTML to Markdown and builds YAML front matter blocks.
 */
final class MarkdownConverter
{
    private ?HtmlConverter $converter = null;

    public function htmlToMarkdown(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        return $this->converter()->convert($html);
    }

    /**
     * @param array<string, scalar|array<int, scalar>|null> $fields
     */
    public function frontMatter(array $fields): string
    {
        $lines = ['---'];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $lines[] = $key . ': ' . $this->yamlValue($value);
        }

        if (count($lines) === 1) {
            return '';
        }

        $lines[] = '---';

        return implode("\n", $lines);
    }

    private function converter(): HtmlConverter
    {
        if ($this->converter === null) {
            $this->converter = new HtmlConverter([
                'header_style' => 'atx',
                'remove_nodes' => 'script style',
            ]);
        }

        return $this->converter;
    }

    /**
     * @param scalar|array<int, scalar> $value
     */
    private function yamlValue(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_map(fn ($item): string => $this->yamlScalar((string) $item), $value)) . ']';
        }

        return $this->yamlScalar((string) $value);
    }

    private function yamlScalar(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
