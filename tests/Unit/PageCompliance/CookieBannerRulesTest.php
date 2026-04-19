<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\PageCompliance;

use PHPUnit\Framework\TestCase;
use Polski\PageCompliance\Model\CheckRule;
use Polski\PageCompliance\PageComplianceService;
use Polski\PageCompliance\Rules\CookieBannerRules;

final class CookieBannerRulesTest extends TestCase
{
    private PageComplianceService $service;

    protected function setUp(): void
    {
        $this->service = new PageComplianceService();
    }

    public function testRuleSetIsNonEmpty(): void
    {
        $rules = CookieBannerRules::all();

        $this->assertNotEmpty($rules);
        $this->assertContainsOnlyInstancesOf(CheckRule::class, $rules);

        $ids = array_map(static fn (CheckRule $r): string => $r->id, $rules);
        $this->assertContains('banner_present', $ids);
        $this->assertContains('accept_button', $ids);
        $this->assertContains('reject_button', $ids);
        $this->assertContains('settings_link', $ids);
    }

    public function testInverseRulesListed(): void
    {
        $inverse = CookieBannerRules::inverseRuleIds();

        $this->assertContains('no_forced_scroll_accept', $inverse);
    }

    public function testWellFormedBannerHtmlPassesMostRules(): void
    {
        $html = <<<HTML
        <div class="cc-banner">
            <p>Ta strona uzywa cookies. Kliknij Akceptuje albo Odrzuc wszystkie.</p>
            <p>Mozesz zmienic ustawienia cookies w dowolnym momencie.</p>
            <p>Szczegoly w <a href="/polityka-prywatnosci">Polityce prywatnosci</a>.</p>
            <div>Kategorie: Niezbedne, Analityka, Marketing.</div>
            <button>Akceptuje wszystkie</button>
            <button>Odrzuc wszystkie</button>
            <button>Ustawienia cookies</button>
        </div>
        HTML;

        $normalized = $this->service->normalize($html);
        $rules = CookieBannerRules::all();
        $inverse = CookieBannerRules::inverseRuleIds();

        $passed = 0;
        foreach ($rules as $rule) {
            $result = $this->service->evaluate($rule, $normalized);

            if (in_array($rule->id, $inverse, true)) {
                if (! $result->passed) {
                    ++$passed;
                }
                continue;
            }

            if ($result->passed) {
                ++$passed;
            }
        }

        $total = count($rules);
        $this->assertGreaterThanOrEqual((int) round($total * 0.7), $passed);
    }

    public function testImpliedConsentPhraseTriggersInverseRule(): void
    {
        $html = '<div class="cc-banner">Klikajac dowolny link na stronie akceptujesz cookies.</div>';
        $normalized = $this->service->normalize($html);

        $rule = null;
        foreach (CookieBannerRules::all() as $r) {
            if ($r->id === 'no_forced_scroll_accept') {
                $rule = $r;
                break;
            }
        }

        $this->assertNotNull($rule);
        $result = $this->service->evaluate($rule, $normalized);

        // Forward result says the phrase WAS found; service flips it for inverse rules.
        $this->assertTrue($result->passed, 'Pattern should be matched in the raw text.');
    }

    public function testNoBannerHtmlProducesLowScore(): void
    {
        $html = '<html><body><h1>Welcome</h1><p>Normal page content.</p></body></html>';
        $normalized = $this->service->normalize($html);

        $matches = 0;
        foreach (CookieBannerRules::all() as $rule) {
            if ($this->service->evaluate($rule, $normalized)->passed) {
                ++$matches;
            }
        }

        $this->assertLessThanOrEqual(1, $matches);
    }
}
