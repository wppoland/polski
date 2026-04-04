<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polski\Enum\CheckboxContext;
use Polski\Enum\ConsentType;
use Polski\Model\LegalCheckbox;
use Polski\Service\CheckboxService;

final class CheckboxServiceTest extends TestCase
{
    private function createService(): CheckboxService
    {
        return new CheckboxService();
    }

    private function makeCheckbox(
        string $id,
        string $label = '',
        ConsentType $type = ConsentType::Required,
        array $contexts = [],
        int $priority = 10,
        bool $enabled = true,
        bool $isCore = false,
    ): LegalCheckbox {
        return new LegalCheckbox(
            id: $id,
            label: $label,
            type: $type,
            contexts: $contexts,
            priority: $priority,
            enabled: $enabled,
            isCore: $isCore,
        );
    }

    // ── register / get / unregister ─────────────────────────────────────

    public function testRegisterAndGetCheckbox(): void
    {
        $service = $this->createService();
        $cb = $this->makeCheckbox('terms', 'Accept terms');

        $service->register($cb);

        $this->assertSame($cb, $service->get('terms'));
    }

    public function testGetReturnsNullForUnknownId(): void
    {
        $service = $this->createService();

        $this->assertNull($service->get('nonexistent'));
    }

    public function testUnregisterRemovesCheckbox(): void
    {
        $service = $this->createService();
        $service->register($this->makeCheckbox('terms'));

        $service->unregister('terms');

        $this->assertNull($service->get('terms'));
    }

    public function testAllReturnsAllRegistered(): void
    {
        $service = $this->createService();
        $service->register($this->makeCheckbox('a'));
        $service->register($this->makeCheckbox('b'));
        $service->register($this->makeCheckbox('c'));

        $this->assertCount(3, $service->all());
        $this->assertArrayHasKey('a', $service->all());
        $this->assertArrayHasKey('b', $service->all());
        $this->assertArrayHasKey('c', $service->all());
    }

    // ── isCore / getCoreIds ─────────────────────────────────────────────

    public function testIsCoreReturnsTrueForBuiltInIds(): void
    {
        $service = $this->createService();

        $this->assertTrue($service->isCore('terms'));
        $this->assertTrue($service->isCore('privacy'));
        $this->assertTrue($service->isCore('withdrawal'));
        $this->assertTrue($service->isCore('digital_waiver'));
        $this->assertTrue($service->isCore('marketing'));
    }

    public function testIsCoreReturnsFalseForCustomIds(): void
    {
        $service = $this->createService();

        $this->assertFalse($service->isCore('newsletter'));
        $this->assertFalse($service->isCore('custom_consent'));
        $this->assertFalse($service->isCore(''));
    }

    public function testGetCoreIdsReturnsSevenBuiltInIds(): void
    {
        $service = $this->createService();
        $coreIds = $service->getCoreIds();

        $this->assertCount(7, $coreIds);
        $this->assertContains('terms', $coreIds);
        $this->assertContains('privacy', $coreIds);
        $this->assertContains('withdrawal', $coreIds);
        $this->assertContains('digital_waiver', $coreIds);
        $this->assertContains('parcel_delivery', $coreIds);
        $this->assertContains('review_reminder', $coreIds);
        $this->assertContains('marketing', $coreIds);
    }

    // ── getForContext ────────────────────────────────────────────────────

    public function testGetForContextFiltersAndSortsByPriority(): void
    {
        $service = $this->createService();

        $service->register($this->makeCheckbox('c', priority: 30, contexts: [CheckboxContext::Checkout]));
        $service->register($this->makeCheckbox('a', priority: 10, contexts: [CheckboxContext::Checkout]));
        $service->register($this->makeCheckbox('b', priority: 20, contexts: [CheckboxContext::Checkout]));
        $service->register($this->makeCheckbox('d', priority: 5, contexts: [CheckboxContext::Registration]));

        $result = $service->getForContext(CheckboxContext::Checkout);

        $this->assertCount(3, $result);
        $this->assertSame('a', $result[0]->id);
        $this->assertSame('b', $result[1]->id);
        $this->assertSame('c', $result[2]->id);
    }

    public function testGetForContextExcludesDisabledCheckboxes(): void
    {
        $service = $this->createService();

        $service->register($this->makeCheckbox('enabled', contexts: [CheckboxContext::Checkout], enabled: true));
        $service->register($this->makeCheckbox('disabled', contexts: [CheckboxContext::Checkout], enabled: false));

        $result = $service->getForContext(CheckboxContext::Checkout);

        $this->assertCount(1, $result);
        $this->assertSame('enabled', $result[0]->id);
    }

    public function testGetForContextReturnsEmptyForContextWithNoCheckboxes(): void
    {
        $service = $this->createService();

        $service->register($this->makeCheckbox('checkout_only', contexts: [CheckboxContext::Checkout]));

        $result = $service->getForContext(CheckboxContext::Registration);

        $this->assertCount(0, $result);
    }

    // ── getComplianceStats ──────────────────────────────────────────────

    public function testComplianceStatsFullScoreWhenAllKeyCheckboxesEnabled(): void
    {
        $service = $this->createService();

        // Register the key checkboxes with proper configurations for max score.
        $service->register($this->makeCheckbox('terms', isCore: true, enabled: true));
        $service->register($this->makeCheckbox('privacy', isCore: true, enabled: true));
        $service->register($this->makeCheckbox('withdrawal', isCore: true, enabled: true));
        $service->register($this->makeCheckbox('digital_waiver', isCore: true, enabled: true));
        $service->register(new LegalCheckbox(
            id: 'marketing',
            label: 'Marketing',
            type: ConsentType::Optional,
            contexts: [],
            enabled: true,
            isCore: true,
        ));
        $service->register(new LegalCheckbox(
            id: 'review_reminder',
            label: 'Review',
            type: ConsentType::Optional,
            contexts: [],
            enabled: true,
            isCore: true,
        ));

        $stats = $service->getComplianceStats();

        // 25 + 25 + 20 + 10 + 10 + 5 + 5 (consent logging) = 100
        $this->assertSame(100, $stats['compliance_score']);
        $this->assertSame('A', $stats['compliance_grade']);
        $this->assertEmpty($stats['suggestions']);
    }

    public function testComplianceStatsLowScoreWhenNoCheckboxesRegistered(): void
    {
        $service = $this->createService();

        $stats = $service->getComplianceStats();

        // Only 5 points for built-in consent logging.
        $this->assertSame(5, $stats['compliance_score']);
        $this->assertSame('F', $stats['compliance_grade']);
        $this->assertNotEmpty($stats['suggestions']);
    }

    public function testComplianceStatsCountsCoreAndCustomSeparately(): void
    {
        $service = $this->createService();

        $service->register($this->makeCheckbox('terms', isCore: true, enabled: true));
        $service->register($this->makeCheckbox('custom_one', isCore: false, enabled: true));
        $service->register($this->makeCheckbox('custom_two', isCore: false, enabled: false));

        $stats = $service->getComplianceStats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['core_total']);
        $this->assertSame(2, $stats['custom_total']);
        $this->assertSame(1, $stats['core_enabled']);
        $this->assertSame(1, $stats['custom_enabled']);
        $this->assertSame(1, $stats['disabled']);
    }

    public function testComplianceStatsSuggestsFixWhenMarketingIsRequired(): void
    {
        $service = $this->createService();

        // Marketing set to required is a GDPR violation.
        $service->register(new LegalCheckbox(
            id: 'marketing',
            label: 'Marketing',
            type: ConsentType::Required,
            contexts: [],
            enabled: true,
            isCore: true,
        ));

        $stats = $service->getComplianceStats();
        $suggestionIds = array_column($stats['suggestions'], 'id');

        $this->assertContains('marketing_not_required', $suggestionIds);
    }

    public function testComplianceGradeMapping(): void
    {
        $service = $this->createService();

        // With terms (25) + privacy (25) + consent logging (5) = 55 -> D
        $service->register($this->makeCheckbox('terms', isCore: true, enabled: true));
        $service->register($this->makeCheckbox('privacy', isCore: true, enabled: true));

        $stats = $service->getComplianceStats();

        $this->assertSame(55, $stats['compliance_score']);
        $this->assertSame('D', $stats['compliance_grade']);
    }

    // ── initCheckboxes idempotent ───────────────────────────────────────

    public function testInitCheckboxesRegistersDefaultsOnce(): void
    {
        $service = $this->createService();

        $service->initCheckboxes();
        $countAfterFirst = count($service->all());

        $service->initCheckboxes();
        $countAfterSecond = count($service->all());

        $this->assertSame($countAfterFirst, $countAfterSecond);
        $this->assertSame(7, $countAfterFirst);
    }
}
