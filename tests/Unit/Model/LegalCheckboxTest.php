<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Polski\Enum\CheckboxContext;
use Polski\Enum\ConsentType;
use Polski\Model\LegalCheckbox;

final class LegalCheckboxTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $cb = new LegalCheckbox(
            id: 'terms',
            label: 'Accept terms',
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout],
            priority: 1,
            enabled: true,
            errorMessage: 'Must accept',
            description: 'Terms checkbox',
            isCore: true,
        );

        $this->assertSame('terms', $cb->id);
        $this->assertSame('Accept terms', $cb->label);
        $this->assertSame(ConsentType::Required, $cb->type);
        $this->assertSame([CheckboxContext::Checkout], $cb->contexts);
        $this->assertSame(1, $cb->priority);
        $this->assertTrue($cb->enabled);
        $this->assertSame('Must accept', $cb->errorMessage);
        $this->assertTrue($cb->isCore);
    }

    public function testGetFieldName(): void
    {
        $cb = new LegalCheckbox(
            id: 'privacy',
            label: '',
            type: ConsentType::Required,
            contexts: [],
        );

        $this->assertSame('polski_checkbox_privacy', $cb->getFieldName());
    }

    public function testGetHtmlIdDefaultsToFieldName(): void
    {
        $cb = new LegalCheckbox(id: 'test', label: '', type: ConsentType::Required, contexts: []);

        $this->assertSame('polski_checkbox_test', $cb->getHtmlId());
    }

    public function testGetHtmlIdUsesCustomWhenSet(): void
    {
        $cb = new LegalCheckbox(id: 'test', label: '', type: ConsentType::Required, contexts: [], htmlId: 'custom-id');

        $this->assertSame('custom-id', $cb->getHtmlId());
    }

    public function testIsVisibleInReturnsTrueWhenEnabledAndContextMatches(): void
    {
        $cb = new LegalCheckbox(
            id: 'terms',
            label: '',
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout, CheckboxContext::PayForOrder],
            enabled: true,
        );

        $this->assertTrue($cb->isVisibleIn(CheckboxContext::Checkout));
        $this->assertTrue($cb->isVisibleIn(CheckboxContext::PayForOrder));
        $this->assertFalse($cb->isVisibleIn(CheckboxContext::Registration));
    }

    public function testIsVisibleInReturnsFalseWhenDisabled(): void
    {
        $cb = new LegalCheckbox(
            id: 'terms',
            label: '',
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout],
            enabled: false,
        );

        $this->assertFalse($cb->isVisibleIn(CheckboxContext::Checkout));
    }

    public function testIsRequiredForRequiredType(): void
    {
        $required = new LegalCheckbox(id: 'a', label: '', type: ConsentType::Required, contexts: []);
        $optional = new LegalCheckbox(id: 'b', label: '', type: ConsentType::Optional, contexts: []);

        $this->assertTrue($required->isRequired());
        $this->assertFalse($optional->isRequired());
    }

    public function testPassesConditionsWithEmptyFilters(): void
    {
        $cb = new LegalCheckbox(id: 'a', label: '', type: ConsentType::Required, contexts: []);

        $this->assertTrue($cb->passesConditions([]));
    }

    public function testPassesConditionsFiltersByCategory(): void
    {
        $cb = new LegalCheckbox(
            id: 'a',
            label: '',
            type: ConsentType::Required,
            contexts: [],
            categories: [10, 20],
        );

        $this->assertTrue($cb->passesConditions(['category_ids' => [10, 30]]));
        $this->assertFalse($cb->passesConditions(['category_ids' => [30, 40]]));
    }

    public function testPassesConditionsFiltersByCountry(): void
    {
        $cb = new LegalCheckbox(
            id: 'a',
            label: '',
            type: ConsentType::Required,
            contexts: [],
            countries: ['PL', 'DE'],
        );

        $this->assertTrue($cb->passesConditions(['country' => 'PL']));
        $this->assertFalse($cb->passesConditions(['country' => 'US']));
        $this->assertTrue($cb->passesConditions(['country' => ''])); // empty = no filter
    }

    public function testPassesConditionsFiltersByPaymentMethod(): void
    {
        $cb = new LegalCheckbox(
            id: 'a',
            label: '',
            type: ConsentType::Required,
            contexts: [],
            paymentMethods: ['bacs', 'cod'],
        );

        $this->assertTrue($cb->passesConditions(['payment_method' => 'bacs']));
        $this->assertFalse($cb->passesConditions(['payment_method' => 'stripe']));
    }

    public function testPassesConditionsFiltersByProductType(): void
    {
        $cb = new LegalCheckbox(
            id: 'a',
            label: '',
            type: ConsentType::Required,
            contexts: [],
            productTypes: ['downloadable'],
        );

        $this->assertTrue($cb->passesConditions(['product_types' => ['downloadable', 'simple']]));
        $this->assertFalse($cb->passesConditions(['product_types' => ['simple']]));
    }

    public function testApplyOverridesChangesLabel(): void
    {
        $cb = new LegalCheckbox(id: 'a', label: 'Original', type: ConsentType::Required, contexts: []);

        $cb->applyOverrides(['label' => 'Updated']);

        $this->assertSame('Updated', $cb->label);
    }

    public function testApplyOverridesChangesMultipleFields(): void
    {
        $cb = new LegalCheckbox(
            id: 'a',
            label: 'Original',
            type: ConsentType::Required,
            contexts: [CheckboxContext::Checkout],
            priority: 10,
            enabled: true,
        );

        $cb->applyOverrides([
            'type' => 'optional',
            'priority' => 5,
            'enabled' => false,
            'html_classes' => 'custom-class',
            'hide_input' => true,
        ]);

        $this->assertSame(ConsentType::Optional, $cb->type);
        $this->assertSame(5, $cb->priority);
        $this->assertFalse($cb->enabled);
        $this->assertSame('custom-class', $cb->htmlClasses);
        $this->assertTrue($cb->hideInput);
    }

    public function testApplyOverridesIgnoresEmptyLabel(): void
    {
        $cb = new LegalCheckbox(id: 'a', label: 'Keep this', type: ConsentType::Required, contexts: []);

        $cb->applyOverrides(['label' => '']);

        $this->assertSame('Keep this', $cb->label);
    }

    public function testToArrayAndFromArrayRoundtrip(): void
    {
        $original = new LegalCheckbox(
            id: 'test',
            label: '<a href="#">Link</a>',
            type: ConsentType::Optional,
            contexts: [CheckboxContext::Checkout, CheckboxContext::Registration],
            priority: 5,
            enabled: true,
            errorMessage: 'Error!',
            description: 'Test checkbox',
            isCore: true,
            htmlClasses: 'my-class',
            hideInput: true,
            categories: [1, 2],
            countries: ['PL'],
            paymentMethods: ['bacs'],
            productTypes: ['simple'],
        );

        $array = $original->toArray();
        $restored = LegalCheckbox::fromArray($array);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->label, $restored->label);
        $this->assertSame($original->type, $restored->type);
        $this->assertSame($original->priority, $restored->priority);
        $this->assertSame($original->enabled, $restored->enabled);
        $this->assertSame($original->errorMessage, $restored->errorMessage);
        $this->assertSame($original->isCore, $restored->isCore);
        $this->assertSame($original->htmlClasses, $restored->htmlClasses);
        $this->assertSame($original->hideInput, $restored->hideInput);
        $this->assertSame($original->categories, $restored->categories);
        $this->assertSame($original->countries, $restored->countries);
        $this->assertSame($original->paymentMethods, $restored->paymentMethods);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $cb = LegalCheckbox::fromArray(['id' => 'minimal']);

        $this->assertSame('minimal', $cb->id);
        $this->assertSame('', $cb->label);
        $this->assertSame(ConsentType::Required, $cb->type);
        $this->assertSame([], $cb->contexts);
        $this->assertSame(10, $cb->priority);
        $this->assertTrue($cb->enabled);
    }
}
