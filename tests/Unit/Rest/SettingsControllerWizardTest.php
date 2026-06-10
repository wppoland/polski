<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use Polski\Rest\SettingsController;

final class SettingsControllerWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['polski_test_options'] = [];
    }

    public function testCompleteWizardMergesModulesInsteadOfResettingThem(): void
    {
        $GLOBALS['polski_test_options'] = [
            'polski_general' => [],
            'polski_checkout' => [],
            'polski_omnibus' => [],
            'polski_withdrawal' => [],
            'polski_modules' => [
                'wishlist' => true,
                'ajax_search' => true,
                'compare' => false,
            ],
        ];

        $request = new \WP_REST_Request('POST', '/polski/v1/wizard/complete');
        $request->set_json_params([
            'company_name' => 'Test Store',
            'company_email' => 'owner@example.test',
            'terms_enabled' => true,
            'privacy_enabled' => true,
            'withdrawal_enabled' => true,
            'marketing_enabled' => false,
            'digital_waiver_enabled' => false,
            'generate_legal_pages' => false,
            'omnibus_enabled' => false,
            'oss_observer_enabled' => false,
            'order_button_text' => 'Zamawiam z obowiązkiem zapłaty',
            'withdrawal_create_lookup_page' => false,
        ]);

        $response = (new SettingsController())->completeWizard($request);

        $this->assertSame(200, $response->get_status());

        $modules = $GLOBALS['polski_test_options']['polski_modules'];
        $this->assertTrue($modules['wishlist']);
        $this->assertTrue($modules['ajax_search']);
        $this->assertFalse($modules['compare']);
        $this->assertFalse($modules['omnibus']);
        $this->assertFalse($modules['oss_observer']);
        $this->assertTrue($modules['legal_checkboxes']);
        $this->assertTrue($modules['withdrawal']);
        $this->assertTrue($modules['checkout_button']);
        $this->assertTrue($modules['tax_display']);
        $this->assertTrue($GLOBALS['polski_test_options']['polski_wizard_complete']);
    }
}
