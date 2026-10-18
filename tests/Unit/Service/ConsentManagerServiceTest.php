<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\ConsentManagerService;

final class ConsentManagerServiceTest extends TestCase
{
    private ConsentManagerService $service;

    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [];
        $this->service = new ConsentManagerService();
    }

    public function testDetectEmbedProviderRecognizesKnownServices(): void
    {
        self::assertSame('YouTube', $this->service->detectEmbedProvider('https://www.youtube.com/embed/dQw4w9WgXcQ'));
        self::assertSame('YouTube', $this->service->detectEmbedProvider('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'));
        self::assertSame('YouTube', $this->service->detectEmbedProvider('https://youtu.be/dQw4w9WgXcQ'));
        self::assertSame('Vimeo', $this->service->detectEmbedProvider('https://player.vimeo.com/video/123456789'));
        self::assertSame('Google Maps', $this->service->detectEmbedProvider('https://www.google.com/maps/embed?pb=!1m18!1m12'));
        self::assertSame('Google Maps', $this->service->detectEmbedProvider('https://maps.google.com/maps?q=Warszawa'));
        self::assertSame('OpenStreetMap', $this->service->detectEmbedProvider('https://www.openstreetmap.org/export/embed.html?bbox=20.9%2C52.2'));
        self::assertSame('SoundCloud', $this->service->detectEmbedProvider('https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com'));
    }

    public function testDetectEmbedProviderReturnsNullForUnknownOrInternal(): void
    {
        self::assertNull($this->service->detectEmbedProvider('https://example.com/internal-frame'));
        self::assertNull($this->service->detectEmbedProvider('/relative-path/frame.html'));
    }

    public function testFilterContentEmbedsTransformsMatchingIframes(): void
    {
        $html = '<p>Check our video:</p><iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Demo Video" allowfullscreen></iframe><p>Footer</p>';
        $result = $this->service->filterContentEmbeds($html);

        self::assertStringNotContainsString('<iframe width="560"', $result);
        self::assertStringContainsString('class="polski-content-blocker"', $result);
        self::assertStringContainsString('data-polski-blocker="marketing"', $result);
        self::assertStringContainsString('data-polski-src="https://www.youtube.com/embed/dQw4w9WgXcQ"', $result);
        self::assertStringContainsString('YouTube', $result);
        self::assertStringContainsString('data-polski-unblock="once"', $result);
        self::assertStringContainsString('data-polski-unblock="always"', $result);
    }

    public function testFilterContentEmbedsLeavesNonMatchingIframesIntact(): void
    {
        $html = '<p>Internal:</p><iframe src="https://example.com/embed"></iframe>';
        $result = $this->service->filterContentEmbeds($html);

        self::assertSame($html, $result);
    }

    public function testFilterContentEmbedsRespectsDisabledSetting(): void
    {
        $GLOBALS['polski_test_options'][ConsentManagerService::OPTION] = [
            'block_embeds' => false,
        ];

        $html = '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
        $result = $this->service->filterContentEmbeds($html);

        self::assertSame($html, $result);
    }
}
