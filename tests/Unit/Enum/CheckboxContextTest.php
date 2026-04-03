<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Polski\Enum\CheckboxContext;
use Polski\Enum\ConsentType;

final class CheckboxContextTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = CheckboxContext::cases();

        $this->assertCount(5, $cases);
        $this->assertSame('checkout', CheckboxContext::Checkout->value);
        $this->assertSame('registration', CheckboxContext::Registration->value);
        $this->assertSame('review', CheckboxContext::Review->value);
        $this->assertSame('pay_for_order', CheckboxContext::PayForOrder->value);
        $this->assertSame('quote', CheckboxContext::Quote->value);
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(CheckboxContext::Checkout, CheckboxContext::tryFrom('checkout'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(CheckboxContext::tryFrom('invalid'));
    }

    public function testConsentTypeCases(): void
    {
        $this->assertSame('required', ConsentType::Required->value);
        $this->assertSame('optional', ConsentType::Optional->value);
        $this->assertCount(2, ConsentType::cases());
    }
}
