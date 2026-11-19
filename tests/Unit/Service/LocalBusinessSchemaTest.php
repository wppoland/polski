<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Hook\LocalBusinessHooks;
use Polski\Service\DeliveryTimeService;
use Polski\Service\LocalBusinessSchema;
use Polski\Service\MerchantPolicySchema;

final class LocalBusinessSchemaTest extends TestCase
{
    private const SETTINGS = [
        'type' => 'HomeAndConstructionBusiness',
        'name' => '3 Tarasy',
        'street' => 'ul. Przykladowa 1',
        'postal_code' => '30-001',
        'city' => 'Krakow',
        'country' => 'pl',
        'telephone' => '+48 12 000 00 00',
        'latitude' => '50,06143',
        'longitude' => '19.93658',
        'opening_hours' => "Pn-Pt 08:00-16:00\nSa 9:00-13:00",
        'area_served' => 'Krakow, Wieliczka',
        'price_range' => '$$',
        'same_as' => "https://www.facebook.com/example\nnot a url",
    ];

    protected function tearDown(): void
    {
        unset($GLOBALS['polski_test_options'], $GLOBALS['polski_test_front_page']);
    }

    public function testFullNode(): void
    {
        $node = LocalBusinessSchema::buildNode(self::SETTINGS, 'https://example.test/', 'https://example.test/logo.png');

        $this->assertSame('HomeAndConstructionBusiness', $node['@type'] ?? null);
        $this->assertSame('https://example.test/#localbusiness', $node['@id'] ?? null);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => 'ul. Przykladowa 1',
            'postalCode' => '30-001',
            'addressLocality' => 'Krakow',
            'addressCountry' => 'PL',
        ], $node['address'] ?? null);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 50.06143, 'longitude' => 19.93658], $node['geo'] ?? null);
        $this->assertSame(['Krakow', 'Wieliczka'], $node['areaServed'] ?? null);
        $this->assertSame(['https://www.facebook.com/example'], $node['sameAs'] ?? null);
        $this->assertSame('$$', $node['priceRange'] ?? null);
        $this->assertSame('https://example.test/logo.png', $node['image'] ?? null);
        $this->assertCount(2, $node['openingHoursSpecification'] ?? []);
    }

    public function testUnknownTypeFallsBackAndAddressIsRequired(): void
    {
        $this->assertSame('LocalBusiness', LocalBusinessSchema::buildNode(['type' => 'Spaceport'] + self::SETTINGS, 'https://example.test/')['@type'] ?? null);
        $this->assertNull(LocalBusinessSchema::buildNode(['name' => 'X'], 'https://example.test/'));
        $this->assertNull(LocalBusinessSchema::buildNode(['city' => 'Krakow'], 'https://example.test/'));
    }

    public function testOpeningHours(): void
    {
        $this->assertSame([
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '08:00', 'closes' => '16:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Saturday', 'opens' => '09:00', 'closes' => '13:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Wednesday'], 'opens' => '10:00', 'closes' => '12:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Saturday', 'Sunday', 'Monday'], 'opens' => '07:00', 'closes' => '09:00'],
        ], LocalBusinessSchema::parseOpeningHours("Mo-Fr 08:00-16:00\nSo 9:00 - 13:00\nPn, Śr 10:00-12:00\nSa-Mo 07:00-09:00\nXx 10:00-11:00\nMo 25:00-26:00\nclosed"));
    }

    public function testMergeIntoYoastOrganizationKeepsOneOrganization(): void
    {
        $merged = LocalBusinessSchema::mergeInto(
            ['@type' => 'Organization', '@id' => 'https://example.test/#organization', 'name' => 'Yoast Name', 'sameAs' => ['https://x.test/a']],
            (array) LocalBusinessSchema::buildNode(self::SETTINGS, 'https://example.test/'),
        );

        $this->assertSame(['Organization', 'HomeAndConstructionBusiness'], $merged['@type']);
        $this->assertSame('https://example.test/#organization', $merged['@id']);
        $this->assertSame('Yoast Name', $merged['name']);
        $this->assertSame(['https://x.test/a', 'https://www.facebook.com/example'], $merged['sameAs']);
        $this->assertSame('Krakow', $merged['address']['addressLocality']);
    }

    public function testYoastGraphGetsBusinessAndReturnPolicyOnTheOrganization(): void
    {
        $GLOBALS['polski_test_front_page'] = true;
        $GLOBALS['polski_test_options'] = [
            'polski_modules' => ['local_business' => true, 'schema_org' => true],
            'polski_local_business' => self::SETTINGS,
            'polski_seo' => ['schema_return_policy' => true],
            'woocommerce_default_country' => 'PL',
        ];

        $hooks = new LocalBusinessHooks(new LocalBusinessSchema(), new MerchantPolicySchema(new DeliveryTimeService()));
        $graph = $hooks->filterYoastGraph([
            ['@type' => 'WebSite', '@id' => 'https://example.test/#website', 'publisher' => ['@id' => 'https://example.test/#organization']],
            ['@type' => 'Organization', '@id' => 'https://example.test/#organization', 'name' => 'Shop'],
        ]);

        $this->assertCount(2, $graph, 'merged, not appended');
        $this->assertSame(['Organization', 'HomeAndConstructionBusiness'], $graph[1]['@type']);
        $this->assertSame('MerchantReturnPolicy', $graph[1]['hasMerchantReturnPolicy']['@type']);

        // Printed into the graph, so the footer fallback stays silent.
        ob_start();
        $hooks->printStandalone();
        $this->assertSame('', ob_get_clean());
    }

    public function testWithoutYoastTheNodeIsPrintedOnItsOwn(): void
    {
        $GLOBALS['polski_test_front_page'] = true;
        $GLOBALS['polski_test_options'] = [
            'polski_modules' => ['local_business' => true],
            'polski_local_business' => self::SETTINGS,
        ];

        $hooks = new LocalBusinessHooks(new LocalBusinessSchema(), new MerchantPolicySchema(new DeliveryTimeService()));
        ob_start();
        $hooks->printStandalone();
        $html = (string) ob_get_clean();

        $this->assertStringStartsWith('<script type="application/ld+json">', $html);
        $json = json_decode(substr($html, 35, -10), true);
        $this->assertSame('https://schema.org', $json['@context'] ?? null);
        $this->assertSame('HomeAndConstructionBusiness', $json['@type'] ?? null);
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $json, 'return policy is opt-in');
    }

    public function testNotOnOtherPages(): void
    {
        $GLOBALS['polski_test_options'] = ['polski_modules' => ['local_business' => true], 'polski_local_business' => self::SETTINGS];

        $hooks = new LocalBusinessHooks(new LocalBusinessSchema(), new MerchantPolicySchema(new DeliveryTimeService()));
        ob_start();
        $hooks->printStandalone();
        $this->assertSame('', ob_get_clean());
    }
}
