<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Enum\ConsentCategory;
use Polski\Service\ConsentManagerService;

/**
 * Custom Integrations: let the merchant add their own inline snippets to the
 * page head or footer, each assigned a consent category.
 *
 * Every snippet is emitted through ConsentManagerService::gateScript, so the
 * browser never runs it on load. The Consent Manager front-end controller
 * swaps the placeholder to an executable script only after the visitor has
 * granted the matching category (necessary snippets always activate).
 *
 * The merchant supplies their own code; the plugin makes no outbound HTTP
 * request from PHP and hardcodes no third-party endpoints. These are tools that
 * help store owners load their own snippets responsibly; they do not by
 * themselves guarantee any particular legal outcome.
 */
final class CustomIntegrationsService implements HasHooks
{
    public const OPTION = 'polski_custom_integrations';

    /**
     * Settings key holding the repeatable snippet list (a JSON-encoded array of
     * snippet rows). Stored as a string so it round-trips through the generic
     * ModulesPage save path; decoded on read.
     */
    public const SNIPPETS_KEY = 'snippets';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('custom_integrations')) {
            return;
        }

        add_action('wp_head', [$this, 'printHead'], 30);
        add_action('wp_footer', [$this, 'printFooter'], 30);
    }

    /**
     * Decoded snippet rows. Each row is sanitised on save; this re-validates the
     * shape defensively so a malformed stored value can never produce output.
     *
     * @return list<array{label: string, placement: string, category: string, code: string}>
     */
    public function snippets(): array
    {
        $raw = get_option(self::OPTION, []);
        $raw = is_array($raw) ? $raw : [];

        $stored = $raw[self::SNIPPETS_KEY] ?? '';

        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
        } else {
            $decoded = $stored;
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = isset($row['code']) ? (string) $row['code'] : '';

            if (trim($code) === '') {
                continue;
            }

            $placement = isset($row['placement']) && $row['placement'] === 'head' ? 'head' : 'footer';
            $category = isset($row['category']) ? (string) $row['category'] : ConsentCategory::Necessary->value;

            if (ConsentCategory::tryFrom($category) === null) {
                $category = ConsentCategory::Necessary->value;
            }

            $out[] = [
                'label' => isset($row['label']) ? (string) $row['label'] : '',
                'placement' => $placement,
                'category' => $category,
                'code' => $code,
            ];
        }

        return $out;
    }

    public function printHead(): void
    {
        $this->printPlacement('head');
    }

    public function printFooter(): void
    {
        $this->printPlacement('footer');
    }

    private function printPlacement(string $placement): void
    {
        if (is_admin()) {
            return;
        }

        foreach ($this->snippets() as $snippet) {
            if ($snippet['placement'] !== $placement) {
                continue;
            }

            $tag = $this->buildTag($snippet['category'], $snippet['code']);

            if ($tag !== '') {
                // Already gated/escaped by ConsentManagerService::gateScript.
                echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
    }

    /**
     * Wrap a merchant snippet in a consent-gated placeholder. If the snippet is
     * wrapped in its own <script>...</script> tags, the inner body is used as
     * the gated script content; otherwise the snippet is treated as inline JS.
     */
    public function buildTag(string $category, string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            return '';
        }

        $inner = $this->scriptBody($code);

        if ($inner === '') {
            return '';
        }

        return ConsentManagerService::gateScript($category, $inner);
    }

    /**
     * Extract the executable body from a snippet. A single <script>...</script>
     * wrapper is unwrapped; bare JS is returned as-is. Any leading/trailing
     * markup outside a script tag is dropped so only script content is emitted
     * through the gate (the gate output is always text/plain until granted).
     */
    private function scriptBody(string $code): string
    {
        if (preg_match('#<script\b[^>]*>(.*?)</script>#is', $code, $m) === 1) {
            return trim($m[1]);
        }

        // No <script> wrapper: treat the whole snippet as inline JS body.
        return $code;
    }
}
