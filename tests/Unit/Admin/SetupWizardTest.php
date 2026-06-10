<?php

declare(strict_types=1);

namespace Polski\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Polski\Admin\SetupWizard;

final class SetupWizardTest extends TestCase
{
    public function testAddsSetupTabAfterDashboard(): void
    {
        $wizard = new SetupWizard();

        $tabs = $wizard->addTab([
            'dashboard' => 'Dashboard',
            'modules' => 'Modules',
            'reports' => 'Reports',
        ]);

        $this->assertSame(
            ['dashboard', 'setup', 'modules', 'reports'],
            array_keys($tabs),
        );
        $this->assertSame('Setup', $tabs['setup']);
    }

    public function testRenderMountsReactWizardAtSetupRoute(): void
    {
        $wizard = new SetupWizard();

        ob_start();
        $wizard->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('window.location.hash !== "#/setup-wizard"', $html);
        $this->assertStringContainsString('<div id="polski-admin"></div>', $html);
    }
}
