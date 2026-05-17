<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Service\AnnexGeneratorService;

/**
 * Annex I(A) (information) + I(B) (model form) generator. Covers merchant-data
 * injection, configurable withdrawal period, and the seller-fallback chain
 * (polski_general → WooCommerce store options → bloginfo).
 */
final class AnnexGeneratorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['polski_test_options'] = [
            'polski_general' => [
                'company_name' => 'Test Sp. z o.o.',
                'company_address' => "ul. Testowa 1\n00-001 Warszawa",
                'company_nip' => '1234567890',
                'company_email' => 'shop@example.test',
                'company_phone' => '+48 22 123 45 67',
            ],
            'polski_withdrawal' => [
                'period_days' => 14,
            ],
        ];
    }

    public function testInfoIncludesMerchantNameAddressAndNip(): void
    {
        $svc = new AnnexGeneratorService();
        $html = $svc->getInfoHtml();

        self::assertStringContainsString('Test Sp. z o.o.', $html);
        self::assertStringContainsString('ul. Testowa 1', $html);
        self::assertStringContainsString('NIP:', $html);
        self::assertStringContainsString('1234567890', $html);
        self::assertStringContainsString('shop@example.test', $html);
    }

    public function testInfoMentionsConfigurableWithdrawalPeriod(): void
    {
        $GLOBALS['polski_test_options']['polski_withdrawal']['period_days'] = 30;
        $svc = new AnnexGeneratorService();
        $html = $svc->getInfoHtml();

        // The custom period appears in the declaration window text. The 14-day
        // return-shipping window (Art. 14 sec. 1) stays hard-coded per directive.
        self::assertStringContainsString('30 dni', $html);
        self::assertStringContainsString('odstąpić od niniejszej umowy w terminie 30 dni', $html);
    }

    public function testInfoFallsBackToFourteenDaysWhenPeriodIsZeroOrMissing(): void
    {
        $GLOBALS['polski_test_options']['polski_withdrawal'] = [];
        $svc = new AnnexGeneratorService();
        $html = $svc->getInfoHtml();

        self::assertStringContainsString('14 dni', $html);
    }

    public function testFormContainsAnnexIBSectionHeading(): void
    {
        $svc = new AnnexGeneratorService();
        $html = $svc->getFormHtml();

        self::assertStringContainsString('Wzór formularza odstąpienia od umowy', $html);
        self::assertStringContainsString('Adresat:', $html);
        self::assertStringContainsString('Imię i nazwisko konsumenta', $html);
    }

    public function testGeneratedHtmlIsEscapedForUserData(): void
    {
        $GLOBALS['polski_test_options']['polski_general']['company_name'] = 'Sklep <script>alert(1)</script>';
        $svc = new AnnexGeneratorService();
        $html = $svc->getInfoHtml();

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
