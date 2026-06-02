<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Enum\ConsentCategory;
use Polski\Service\ConsentManagerService;

use const Polski\PLUGIN_DIR;
use const Polski\PLUGIN_FILE;
use const Polski\VERSION;

/**
 * Custom Triggers: push custom dataLayer events on simple page conditions.
 *
 * The merchant defines triggers that push a named event (with optional extra
 * parameters) into the existing GA4 dataLayer when a condition matches:
 *  - `page_url`: the current URL path/query contains a given substring,
 *  - `click`:    an element matching a CSS selector is clicked.
 *
 * A dataLayer push is a first-party action, but each trigger may be assigned a
 * consent category; the front-end controller only fires a categorised trigger
 * once that category is granted by the visitor's consent cookie (necessary
 * always fires) and re-checks on the `polskiConsentChange` event. This lets a
 * merchant gate, for example, a marketing conversion event behind marketing
 * consent.
 *
 * The triggers integrate with the GA4 DataLayer module's `window.dataLayer`.
 * These are tools that help store owners model their own events; they do not by
 * themselves guarantee any particular legal outcome.
 */
final class CustomTriggersService implements HasHooks
{
    public const OPTION = 'polski_custom_triggers';

    /** Settings key holding the repeatable trigger list (JSON-encoded). */
    public const TRIGGERS_KEY = 'triggers';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('custom_triggers')) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueController']);
    }

    /**
     * Decoded, validated trigger rows.
     *
     * @return list<array{event: string, condition: string, value: string, selector: string, category: string, params: array<string, scalar>}>
     */
    public function triggers(): array
    {
        $raw = get_option(self::OPTION, []);
        $raw = is_array($raw) ? $raw : [];

        $stored = $raw[self::TRIGGERS_KEY] ?? '';
        $decoded = is_string($stored) ? json_decode($stored, true) : $stored;

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $event = isset($row['event']) ? trim((string) $row['event']) : '';

            if ($event === '') {
                continue;
            }

            $condition = isset($row['condition']) && $row['condition'] === 'click' ? 'click' : 'page_url';
            $category = isset($row['category']) ? (string) $row['category'] : ConsentCategory::Necessary->value;

            if (ConsentCategory::tryFrom($category) === null) {
                $category = ConsentCategory::Necessary->value;
            }

            $params = [];
            if (isset($row['params']) && is_array($row['params'])) {
                foreach ($row['params'] as $pKey => $pVal) {
                    if (is_scalar($pVal)) {
                        $params[(string) $pKey] = $pVal;
                    }
                }
            }

            $out[] = [
                'event' => $event,
                'condition' => $condition,
                'value' => isset($row['value']) ? (string) $row['value'] : '',
                'selector' => isset($row['selector']) ? (string) $row['selector'] : '',
                'category' => $category,
                'params' => $params,
            ];
        }

        return $out;
    }

    /**
     * Enqueue the vanilla controller and hand it the trigger definitions plus
     * the consent cookie/event names so it can honour categorised triggers.
     */
    public function enqueueController(): void
    {
        if (is_admin()) {
            return;
        }

        $triggers = $this->triggers();

        if ($triggers === []) {
            return;
        }

        $deps = [];
        $version = VERSION;

        $assetFile = PLUGIN_DIR . '/build/frontend-triggers.asset.php';
        if (is_readable($assetFile)) {
            /** @var array{dependencies?: list<string>, version?: string} $asset */
            $asset = require $assetFile;
            $deps = is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
            $version = isset($asset['version']) ? (string) $asset['version'] : VERSION;
        }

        wp_enqueue_script(
            'polski-custom-triggers',
            plugins_url('build/frontend-triggers.js', PLUGIN_FILE),
            $deps,
            $version,
            true,
        );

        wp_localize_script('polski-custom-triggers', 'polskiTriggers', [
            'triggers' => $triggers,
            'cookie' => ConsentManagerService::COOKIE,
            'event' => ConsentManagerService::EVENT,
            'necessary' => ConsentCategory::Necessary->value,
        ]);
    }
}
