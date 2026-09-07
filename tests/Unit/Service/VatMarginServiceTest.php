<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\VatMarginService;

/**
 * The annotation is a phrase Polish law puts on the face of an invoice. Getting
 * it wrong, or dropping it, is a defect in a legal document.
 */
final class VatMarginServiceTest extends TestCase
{
    public function testEachSchemeCarriesItsStatutoryWording(): void
    {
        self::assertSame('procedura marży - towary używane', VatMarginService::annotationFor('used'));
        self::assertSame('procedura marży - dzieła sztuki', VatMarginService::annotationFor('art'));
        self::assertSame(
            'procedura marży - przedmioty kolekcjonerskie i antyki',
            VatMarginService::annotationFor('collectors'),
        );
    }

    public function testDiacriticsAreIntact(): void
    {
        // A stripped "marzy" is not the phrase the regulation names.
        foreach (['used', 'art', 'collectors'] as $scheme) {
            self::assertStringContainsString('marży', VatMarginService::annotationFor($scheme));
        }
    }

    public function testAnUnknownSchemeHasNoWordingRatherThanAGuess(): void
    {
        self::assertSame('', VatMarginService::annotationFor(''));
        self::assertSame('', VatMarginService::annotationFor('nonsense'));
    }

    public function testEverySelectableSchemeExceptNoneHasWording(): void
    {
        foreach (array_keys(VatMarginService::schemes()) as $key) {
            if ($key === '') {
                continue;
            }
            self::assertNotSame('', VatMarginService::annotationFor($key), "no wording for scheme {$key}");
        }
    }

    public function testInvoicePayloadOfAnUnexpectedShapeIsPassedThroughUntouched(): void
    {
        $service = new VatMarginService();
        $order = new \WC_Order();

        self::assertFalse($service->annotateInvoice(false, $order));
        self::assertNull($service->annotateInvoice(null, $order));
    }
}
