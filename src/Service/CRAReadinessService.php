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

        // Matched on the request path, so it works without a rewrite flush.
        add_action('parse_request', [$this, 'serveSecurityTxt']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('cra_readiness');
    }

    /**
     * Serve the security.txt file content when the rewrite matches.
     */
    public function serveSecurityTxt(): void
    {
        $requestPath = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '', PHP_URL_PATH);
        $securityTxtPath = (string) wp_parse_url(home_url('/.well-known/security.txt'), PHP_URL_PATH);

        if ($requestPath !== $securityTxtPath) {
            return;
        }

        $settings = $this->getSettings();
        $contactEmail = sanitize_email((string) ($settings['security_contact'] ?? ''));

        if ($contactEmail === '') {
            $contactEmail = (string) get_option('admin_email');
        }
        $policyUrl = $settings['security_policy_url'] ?? '';
        $expiresDate = $settings['security_txt_expires'] ?? '';

        if (empty($expiresDate)) {
            $expiresDate = gmdate('Y-m-d\TH:i:s\z', strtotime('+1 year'));
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo 'Contact: mailto:' . esc_html(sanitize_email($contactEmail)) . "\n";

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
