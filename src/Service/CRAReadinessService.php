<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Cyber Resilience Act (CRA) readiness tools.
 *
 * - Security contact page (vulnerability disclosure)
 * - Security policy display via /.well-known/security.txt (RFC 9116)
 * - Configurable contact, policy URL, and expiry
 *
 * @author wppoland.com
 */
final class CRAReadinessService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('init', [$this, 'registerSecurityTxtRewrite']);
        add_action('template_redirect', [$this, 'serveSecurityTxt']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('cra_readiness');
    }

    /**
     * Register rewrite rule for /.well-known/security.txt per RFC 9116.
     */
    public function registerSecurityTxtRewrite(): void
    {
        add_rewrite_rule(
            '^\.well-known/security\.txt$',
            'index.php?polski_security_txt=1',
            'top',
        );

        add_filter('query_vars', static fn(array $vars): array => array_merge($vars, ['polski_security_txt']));
    }

    /**
     * Serve the security.txt file content when the rewrite matches.
     */
    public function serveSecurityTxt(): void
    {
        if (! get_query_var('polski_security_txt')) {
            return;
        }

        $settings = $this->getSettings();
        $contactEmail = $settings['security_contact'] ?? get_option('admin_email');
        $policyUrl = $settings['security_policy_url'] ?? '';
        $expiresDate = $settings['security_txt_expires'] ?? '';

        if (empty($expiresDate)) {
            $expiresDate = gmdate('Y-m-d\TH:i:s\z', strtotime('+1 year'));
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo 'Contact: mailto:' . sanitize_email($contactEmail) . "\n";

        if (! empty($policyUrl)) {
            echo 'Policy: ' . esc_url($policyUrl) . "\n";
        }

        echo "Preferred-Languages: pl, en\n";
        echo 'Expires: ' . esc_html($expiresDate) . "\n";
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_cra', []);

        return is_array($settings) ? $settings : [];
    }
}
