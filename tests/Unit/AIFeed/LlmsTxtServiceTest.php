<?php

declare(strict_types=1);

namespace {
    if (! function_exists('get_bloginfo')) {
        function get_bloginfo(string $show = ''): string
        {
            return match ($show) {
                'name' => 'Test Shop',
                'description' => 'A shop description',
                default => '',
            };
        }
    }

    if (! function_exists('get_permalink')) {
        function get_permalink(int $id = 0): string|false
        {
            return $id > 0 ? 'https://shop.test/page/' . $id : false;
        }
    }

    if (! function_exists('get_the_title')) {
        function get_the_title(int $id = 0): string
        {
            return match ($id) {
                10 => 'Terms',
                11 => 'Privacy',
                12 => 'Returns',
                13 => 'Complaints',
                default => '',
            };
        }
    }

    if (! function_exists('add_query_arg')) {
        function add_query_arg(string $key, string $value, string $url): string
        {
            return $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . $value;
        }
    }

    if (! function_exists('apply_filters')) {
        function apply_filters(string $hookName, mixed $value, mixed ...$args): mixed
        {
            unset($hookName, $args);
            return $value;
        }
    }

    if (! function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags(string $text, bool $removeBreaks = false): string
        {
            $text = strip_tags($text);
            if ($removeBreaks) {
                $text = preg_replace('/[\r\n\t ]+/', ' ', $text) ?? $text;
            }
            return trim($text);
        }
    }

    if (! function_exists('taxonomy_exists')) {
        function taxonomy_exists(string $taxonomy): bool
        {
            return false;
        }
    }

    if (! isset($GLOBALS['polski_test_options'])) {
        $GLOBALS['polski_test_options'] = [];
    }
}

namespace Polski\Tests\Unit\AIFeed {

    use PHPUnit\Framework\TestCase;
    use Polski\AIFeed\LlmsTxtService;

    final class LlmsTxtServiceTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['polski_test_options'] = [];
        }

        public function testBuildEmitsTitleDescriptionAndLegalSection(): void
        {
            $GLOBALS['polski_test_options'] = [
                'polski_terms_page_id' => 10,
                'polski_privacy_page_id' => 11,
                'polski_returns_page_id' => 12,
                'polski_complaints_page_id' => 13,
            ];

            $body = (new LlmsTxtService())->build();

            self::assertStringStartsWith('# Test Shop', $body);
            self::assertStringContainsString('> A shop description', $body);
            self::assertStringContainsString('## Legal', $body);
            self::assertStringContainsString('[Terms](', $body);
            self::assertStringContainsString('?output_format=md)', $body);
            self::assertStringContainsString('[Privacy](', $body);
            self::assertStringContainsString(': Terms and conditions', $body);
            self::assertStringContainsString(': Privacy policy', $body);
        }

        public function testBuildSkipsLegalSectionWhenNoPagesExist(): void
        {
            $body = (new LlmsTxtService())->build();

            self::assertStringStartsWith('# Test Shop', $body);
            self::assertStringNotContainsString('## Legal', $body);
        }

        public function testBuildAlwaysEndsWithSingleNewline(): void
        {
            $body = (new LlmsTxtService())->build();

            self::assertStringEndsWith("\n", $body);
            self::assertDoesNotMatchRegularExpression('/\n\n\z/', $body);
        }
    }
}
