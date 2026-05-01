<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\AIFeed;

use PHPUnit\Framework\TestCase;
use Polski\AIFeed\MarkdownConverter;

final class MarkdownConverterTest extends TestCase
{
    public function testHtmlToMarkdownConvertsBasicHtml(): void
    {
        $converter = new MarkdownConverter();

        $markdown = $converter->htmlToMarkdown('<h2>Heading</h2><p>Some <strong>bold</strong> text.</p>');

        $this->assertStringContainsString('## Heading', $markdown);
        $this->assertStringContainsString('**bold**', $markdown);
    }

    public function testHtmlToMarkdownReturnsEmptyStringForEmptyInput(): void
    {
        $converter = new MarkdownConverter();

        $this->assertSame('', $converter->htmlToMarkdown(''));
        $this->assertSame('', $converter->htmlToMarkdown("\n  \t"));
    }

    public function testHtmlToMarkdownStripsScriptAndStyleNodes(): void
    {
        $converter = new MarkdownConverter();

        $markdown = $converter->htmlToMarkdown(
            '<p>Visible</p><script>alert(1)</script><style>body{color:red}</style>'
        );

        $this->assertStringNotContainsString('alert(1)', $markdown);
        $this->assertStringNotContainsString('color:red', $markdown);
        $this->assertStringContainsString('Visible', $markdown);
    }

    public function testFrontMatterEmitsYamlBlockWithDoubleQuotedScalars(): void
    {
        $converter = new MarkdownConverter();

        $block = $converter->frontMatter([
            'title' => 'Hello "world"',
            'permalink' => 'https://example.test/post',
        ]);

        $this->assertStringStartsWith('---', $block);
        $this->assertStringEndsWith('---', $block);
        $this->assertStringContainsString('title: "Hello \\"world\\""', $block);
        $this->assertStringContainsString('permalink: "https://example.test/post"', $block);
    }

    public function testFrontMatterSkipsEmptyAndNullValues(): void
    {
        $converter = new MarkdownConverter();

        $block = $converter->frontMatter([
            'title' => 'Present',
            'sku' => '',
            'gtin' => null,
            'tags' => [],
        ]);

        $this->assertStringContainsString('title:', $block);
        $this->assertStringNotContainsString('sku:', $block);
        $this->assertStringNotContainsString('gtin:', $block);
        $this->assertStringNotContainsString('tags:', $block);
    }

    public function testFrontMatterReturnsEmptyStringWhenAllValuesAreEmpty(): void
    {
        $converter = new MarkdownConverter();

        $this->assertSame('', $converter->frontMatter([
            'title' => '',
            'permalink' => null,
        ]));
    }

    public function testFrontMatterEmitsArrayValueAsYamlFlowSequence(): void
    {
        $converter = new MarkdownConverter();

        $block = $converter->frontMatter([
            'categories' => ['Hoodies', 'T-Shirts'],
        ]);

        $this->assertStringContainsString('categories: ["Hoodies", "T-Shirts"]', $block);
    }
}
