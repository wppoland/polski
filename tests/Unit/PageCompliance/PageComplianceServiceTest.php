<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\PageCompliance;

use PHPUnit\Framework\TestCase;
use Polski\Enum\LegalPageType;
use Polski\PageCompliance\Enum\Severity;
use Polski\PageCompliance\Model\CheckRule;
use Polski\PageCompliance\PageComplianceService;

final class PageComplianceServiceTest extends TestCase
{
    private PageComplianceService $service;

    protected function setUp(): void
    {
        $this->service = new PageComplianceService();
    }

    public function testNormalizeStripsDiacriticsAndLowercases(): void
    {
        $out = $this->service->normalize('Polityka Prywatności - RODO');

        $this->assertSame('polityka prywatnosci - rodo', $out);
    }

    public function testNormalizeStripsHtml(): void
    {
        $out = $this->service->normalize('<p>Prawo <strong>sprzeciwu</strong></p>');

        $this->assertSame('prawo sprzeciwu', $out);
    }

    public function testEvaluatePassesWhenAnyPatternMatches(): void
    {
        $rule = new CheckRule(
            id: 'test',
            label: 'Test',
            severity: Severity::Required,
            patterns: ['alpha', 'beta'],
            hint: 'fix it',
        );

        $result = $this->service->evaluate($rule, 'content with beta in it');

        $this->assertTrue($result->passed);
        $this->assertSame('test', $result->ruleId);
        $this->assertSame('', $result->toArray()['hint']);
    }

    public function testEvaluateFailsWhenNoPatternMatches(): void
    {
        $rule = new CheckRule(
            id: 'x',
            label: 'X',
            severity: Severity::Required,
            patterns: ['gamma'],
            hint: 'do something',
        );

        $result = $this->service->evaluate($rule, 'only alpha here');

        $this->assertFalse($result->passed);
        $this->assertSame('do something', $result->toArray()['hint']);
    }

    public function testEvaluateIgnoresEmptyPatterns(): void
    {
        $rule = new CheckRule(
            id: 'y',
            label: 'Y',
            severity: Severity::Recommended,
            patterns: [''],
            hint: '',
        );

        $result = $this->service->evaluate($rule, 'any content');

        $this->assertFalse($result->passed);
    }

    public function testPrivacyRuleSetIsNonEmpty(): void
    {
        $rules = $this->service->rulesFor(LegalPageType::Privacy);

        $this->assertNotEmpty($rules);
        $this->assertContainsOnlyInstancesOf(CheckRule::class, $rules);

        $ids = array_map(static fn (CheckRule $r): string => $r->id, $rules);
        $this->assertContains('controller_identity', $ids);
        $this->assertContains('legal_basis', $ids);
        $this->assertContains('supervisory_authority', $ids);
        $this->assertContains('subject_right_erasure', $ids);
    }

    public function testRegulaminRuleSetIsNonEmpty(): void
    {
        $rules = $this->service->rulesFor(LegalPageType::Terms);

        $this->assertNotEmpty($rules);
        $ids = array_map(static fn (CheckRule $r): string => $r->id, $rules);
        $this->assertContains('provider_identity', $ids);
        $this->assertContains('withdrawal_right', $ids);
        $this->assertContains('complaints_procedure', $ids);
    }

    public function testUnsupportedTypeReturnsEmptyRules(): void
    {
        $rules = $this->service->rulesFor(LegalPageType::Returns);

        $this->assertSame([], $rules);
    }

    public function testMinLengthRuleFailsOnShortContent(): void
    {
        $rule = new CheckRule(
            id: 'min',
            label: 'Min',
            severity: Severity::Required,
            patterns: ['anything'],
            hint: 'content too short',
            minLength: 100,
        );

        $result = $this->service->evaluate($rule, 'short anything');

        $this->assertFalse($result->passed);
    }

    public function testPrivacyContentWithAllMandatoryPatternsScoresHigh(): void
    {
        $content = $this->service->normalize(implode(' ', [
            'Administratorem danych osobowych jest ACME sp. z o.o.',
            'Kontakt: kontakt@acme.pl',
            'Dane przetwarzane sa w celu realizacji zamowien.',
            'Podstawa prawna: art. 6 ust. 1 lit. b RODO.',
            'Okres przechowywania: 5 lat.',
            'Odbiorcy danych to kurierzy i operatorzy platnosci.',
            'Masz prawo dostepu, sprostowania, usuniecia i ograniczenia przetwarzania.',
            'Masz prawo przenoszenia danych i wniesienia sprzeciwu.',
            'Masz prawo cofniecia zgody w dowolnym momencie.',
            'Masz prawo skargi do Prezesa Urzedu Ochrony Danych Osobowych.',
        ]));

        $report = new \Polski\PageCompliance\Model\CheckReport(
            pageType: 'privacy',
            pageId: 1,
            contentLength: strlen($content),
            results: array_map(
                fn ($rule) => $this->service->evaluate($rule, $content),
                $this->service->rulesFor(LegalPageType::Privacy),
            ),
        );

        $this->assertGreaterThanOrEqual(70, $report->score());
        $this->assertFalse($report->hasMissingRequired());
    }

    public function testEmptyPrivacyContentScoresZeroAndMissing(): void
    {
        $content = '';

        $report = new \Polski\PageCompliance\Model\CheckReport(
            pageType: 'privacy',
            pageId: null,
            contentLength: 0,
            results: array_map(
                fn ($rule) => $this->service->evaluate($rule, $content),
                $this->service->rulesFor(LegalPageType::Privacy),
            ),
        );

        $this->assertLessThan(10, $report->score());
        $this->assertTrue($report->hasMissingRequired());
    }

    public function testReportToArrayShape(): void
    {
        $content = $this->service->normalize('Polityka prywatnosci');
        $rules = array_slice($this->service->rulesFor(LegalPageType::Privacy), 0, 2);

        $results = array_map(
            fn ($rule) => $this->service->evaluate($rule, $content),
            $rules,
        );

        $report = new \Polski\PageCompliance\Model\CheckReport(
            pageType: 'privacy',
            pageId: 42,
            contentLength: 25,
            results: $results,
        );

        $arr = $report->toArray();

        $this->assertSame('privacy', $arr['page_type']);
        $this->assertSame(42, $arr['page_id']);
        $this->assertSame(25, $arr['content_length']);
        $this->assertArrayHasKey('score', $arr);
        $this->assertArrayHasKey('has_missing_required', $arr);
        $this->assertCount(2, $arr['results']);
    }

    // ── forbidden patterns (the "ctrl+f for what should not be there" rules) ──

    private function forbiddenRule(string $id = 'no_giodo'): CheckRule
    {
        return new CheckRule(
            id: $id,
            label: 'Forbidden',
            severity: Severity::Required,
            patterns: ['giodo', 'privacy shield'],
            hint: 'Remove it.',
            minLength: 0,
            forbidden: true,
        );
    }

    public function testForbiddenRuleFailsWhenThePatternIsPresent(): void
    {
        $content = $this->service->normalize('Organem nadzorczym jest GIODO.');

        $this->assertFalse($this->service->evaluate($this->forbiddenRule(), $content)->passed);
    }

    public function testForbiddenRulePassesWhenTheDocumentIsClean(): void
    {
        $content = $this->service->normalize('Organem nadzorczym jest Prezes UODO.');

        $this->assertTrue($this->service->evaluate($this->forbiddenRule(), $content)->passed);
    }

    public function testForbiddenRuleDoesNotPassOnAnEmptyDocument(): void
    {
        // Otherwise an empty page collects points for everything it fails to say.
        $this->assertFalse($this->service->evaluate($this->forbiddenRule(), '')->passed);
    }

    public function testPrivacyRulesCatchARepealedActAndAnInvalidatedTransferMechanism(): void
    {
        $content = $this->service->normalize(
            'Dane przetwarzamy zgodnie z ustawą z dnia 29 sierpnia 1997 r. o ochronie danych osobowych '
            . 'oraz przekazujemy je do USA w oparciu o Privacy Shield. Organ nadzorczy: GIODO.',
        );

        $failed = [];

        foreach ($this->service->rulesFor(LegalPageType::Privacy) as $rule) {
            $result = $this->service->evaluate($rule, $content);

            if (! $result->passed) {
                $failed[] = $result->ruleId;
            }
        }

        $this->assertContains('no_repealed_1997_act', $failed);
        $this->assertContains('no_privacy_shield', $failed);
        $this->assertContains('no_giodo', $failed);
    }

    public function testTermsRulesCatchAnUnfilledTemplate(): void
    {
        $content = $this->service->normalize('Sprzedawcą jest (...) z siedzibą w XXX.');

        $failed = [];

        foreach ($this->service->rulesFor(LegalPageType::Terms) as $rule) {
            $result = $this->service->evaluate($rule, $content);

            if (! $result->passed) {
                $failed[] = $result->ruleId;
            }
        }

        $this->assertContains('no_template_placeholders', $failed);
        $this->assertContains('phone_number', $failed);
        $this->assertContains('nonprofessional_buyer', $failed);
    }
}
