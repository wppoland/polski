<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Enum\ConsentCategory;

use const Polski\PLUGIN_DIR;
use const Polski\PLUGIN_FILE;
use const Polski\VERSION;

/**
 * Consent Manager: a native cookie-consent banner with consent categories,
 * Google Consent Mode v2 signalling, and a contract for gating scripts/iframes
 * until the visitor grants the matching category.
 *
 * Flow on the front end:
 *  - At wp_head priority 0 (before DataLayerService at priority 1) the default
 *    Consent Mode state is printed with everything denied except necessary,
 *    then immediately updated from the stored `polski_consent` cookie if one
 *    exists. This guarantees gtag/GTM see the right state from their first call.
 *  - The banner is rendered in the footer. When the visitor chooses, a small
 *    inline script writes the cookie, calls `gtag('consent','update', ...)`,
 *    fires a `polskiConsentChange` window event so gated tags can react, and
 *    POSTs the decision to the REST recorder.
 *
 * These are tools that help store owners collect and honour consent choices;
 * they do not by themselves guarantee any particular legal outcome.
 */
final class ConsentManagerService implements HasHooks
{
    public const OPTION = 'polski_consent_manager';
    public const COOKIE = 'polski_consent';
    public const EVENT = 'polskiConsentChange';

    /**
     * window/cookie keys for the optional categories. `necessary` is implicit.
     */
    private const CONSENT_MODE_MAP = [
        'analytics' => ['analytics_storage'],
        'marketing' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        'preferences' => ['functionality_storage', 'personalization_storage'],
    ];

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('consent_manager')) {
            return;
        }

        // Consent Mode must initialise before DataLayerService (wp_head @1).
        add_action('wp_head', [$this, 'printConsentMode'], 0);

        // Banner script + styles, and the markup in the footer.
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'renderBanner'], 20);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        return wp_parse_args(
            $saved,
            [
                'category_analytics' => true,
                'category_marketing' => true,
                'category_preferences' => true,
                'heading' => '',
                'banner_text' => __('We use cookies and similar technologies to run this site, measure traffic, and personalise content. You can accept all, reject non-essential ones, or manage your choices.', 'polski'),
                'accept_label' => __('Accept all', 'polski'),
                'reject_label' => __('Reject all', 'polski'),
                'manage_label' => __('Manage', 'polski'),
                'save_label' => __('Save choices', 'polski'),
                'position' => 'bottom',
                'google_consent_mode' => true,
            ],
        );
    }

    /**
     * Enabled optional categories, in display order. `necessary` is always on.
     *
     * @return list<string>
     */
    public function enabledCategories(): array
    {
        $settings = $this->getSettings();

        $list = [];
        foreach (ConsentCategory::optional() as $category) {
            if (! empty($settings['category_' . $category->value])) {
                $list[] = $category->value;
            }
        }

        return $list;
    }

    // ── Google Consent Mode v2 ───────────────────────────────────────────

    /**
     * Print the Consent Mode default (all denied except necessary) and then an
     * immediate update from the stored cookie. Runs before any gtag/GTM code.
     */
    public function printConsentMode(): void
    {
        if (empty($this->getSettings()['google_consent_mode'])) {
            return;
        }

        $denied = [
            'ad_storage' => 'denied',
            'ad_user_data' => 'denied',
            'ad_personalization' => 'denied',
            'analytics_storage' => 'denied',
            'functionality_storage' => 'denied',
            'personalization_storage' => 'denied',
            'security_storage' => 'granted',
            'wait_for_update' => 500,
        ];

        $script = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . 'gtag("consent","default",' . wp_json_encode($denied) . ');';

        $granted = $this->consentModeFromCookie();
        if ($granted !== null) {
            $script .= 'gtag("consent","update",' . wp_json_encode($granted) . ');';
        }

        wp_print_inline_script_tag($script, ['id' => 'polski-consent-mode']);
    }

    /**
     * Build a Consent Mode update payload from the stored cookie, or null when
     * the visitor has not decided yet.
     *
     * @return array<string, string>|null
     */
    private function consentModeFromCookie(): ?array
    {
        $granted = $this->grantedCategories();
        if ($granted === null) {
            return null;
        }

        $payload = [];
        foreach (self::CONSENT_MODE_MAP as $category => $signals) {
            $state = in_array($category, $granted, true) ? 'granted' : 'denied';
            foreach ($signals as $signal) {
                $payload[$signal] = $state;
            }
        }

        return $payload;
    }

    /**
     * Categories granted in the visitor's cookie. `necessary` is always present.
     * Returns null when no valid cookie is set (visitor has not decided).
     *
     * @return list<string>|null
     */
    public function grantedCategories(): ?array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cookie, not a state change.
        $raw = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field((string) wp_unslash($_COOKIE[self::COOKIE])) : '';
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['categories']) || ! is_array($decoded['categories'])) {
            return null;
        }

        $granted = [ConsentCategory::Necessary->value];
        foreach (ConsentCategory::optional() as $category) {
            if (in_array($category->value, $decoded['categories'], true)) {
                $granted[] = $category->value;
            }
        }

        return array_values(array_unique($granted));
    }

    /**
     * Whether a given category is currently granted by the visitor's cookie.
     * `necessary` is always granted.
     */
    public function isGranted(string $category): bool
    {
        if ($category === ConsentCategory::Necessary->value) {
            return true;
        }

        $granted = $this->grantedCategories();

        return $granted !== null && in_array($category, $granted, true);
    }

    // ── Consent-gating contract ──────────────────────────────────────────

    /**
     * Render a script that only runs after the visitor grants `$category`.
     *
     * Other modules opt in by emitting their inline JS through this helper. The
     * markup is `<script type="text/plain" data-polski-consent="CATEGORY">` so
     * the browser never executes it on load; the front-end controller rewrites
     * it to an executable script once the category is granted (immediately if
     * the cookie already allows it, otherwise on the `polskiConsentChange`
     * event).
     *
     * @return string Safe HTML to echo.
     */
    public static function gateScript(string $category, string $inlineJs): string
    {
        // Escape any literal closing tag so a snippet cannot break out of the
        // gated (text/plain) wrapper and execute before consent.
        return sprintf(
            '<script type="text/plain" data-polski-consent="%s">%s</script>',
            esc_attr($category),
            str_replace('</script>', '<\/script>', $inlineJs),
        );
    }

    /**
     * Wrap an external src in a consent-gated script tag.
     *
     * @return string Safe HTML to echo.
     */
    public static function gateSrc(string $category, string $src): string
    {
        return sprintf(
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- inert type="text/plain" consent placeholder, activated by the consent-manager JS only after opt-in; cannot be enqueued.
            '<script type="text/plain" data-polski-consent="%s" data-src="%s"></script>',
            esc_attr($category),
            esc_url($src),
        );
    }

    // ── Banner ───────────────────────────────────────────────────────────

    /**
     * Stable hash of the current banner wording, so each stored decision is tied
     * to the exact text the visitor saw.
     */
    public function consentVersion(): string
    {
        $settings = $this->getSettings();

        $material = wp_json_encode([
            $settings['heading'] ?? '',
            $settings['banner_text'] ?? '',
            $this->enabledCategories(),
        ]);

        return substr(md5((string) $material), 0, 16);
    }

    /**
     * Runtime config consumed by the front-end controller (window.polskiConsent).
     *
     * @return array<string, mixed>
     */
    private function bannerConfig(): array
    {
        $settings = $this->getSettings();

        return [
            'cookie' => self::COOKIE,
            'event' => self::EVENT,
            'version' => $this->consentVersion(),
            'categories' => $this->enabledCategories(),
            'consentMode' => ! empty($settings['google_consent_mode']),
            'consentModeMap' => self::CONSENT_MODE_MAP,
            'restUrl' => esc_url_raw(rest_url('polski/v1/consent')),
            'nonce' => wp_create_nonce('wp_rest'),
        ];
    }

    /**
     * Enqueue the banner bundle + styles early on the storefront and localise
     * the runtime config, REST URL, and nonce. Reads the built asset.php for the
     * script dependencies and version when present.
     */
    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }

        $deps = [];
        $version = VERSION;

        $assetFile = PLUGIN_DIR . '/build/frontend-consent.asset.php';
        if (is_readable($assetFile)) {
            /** @var array{dependencies?: list<string>, version?: string} $asset */
            $asset = require $assetFile;
            $deps = is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
            $version = isset($asset['version']) ? (string) $asset['version'] : VERSION;
        }

        wp_enqueue_style(
            'polski-consent-banner',
            plugins_url('assets/css/consent-banner.css', PLUGIN_FILE),
            [],
            VERSION,
        );

        wp_enqueue_script(
            'polski-consent-banner',
            plugins_url('build/frontend-consent.js', PLUGIN_FILE),
            $deps,
            $version,
            true,
        );

        wp_localize_script('polski-consent-banner', 'polskiConsent', $this->bannerConfig());
    }

    public function renderBanner(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = $this->getSettings();
        $categories = $this->enabledCategories();
        $position = in_array($settings['position'] ?? 'bottom', ['top', 'bottom', 'center'], true)
            ? (string) $settings['position']
            : 'bottom';

        $heading = (string) ($settings['heading'] ?? '');
        ?>
        <div
            id="polski-consent-banner"
            class="polski-consent-banner polski-consent-banner--<?php echo esc_attr($position); ?>"
            role="dialog"
            aria-modal="false"
            aria-label="<?php esc_attr_e('Consent settings', 'polski'); ?>"
            hidden
        >
            <div class="polski-consent-banner__body">
                <?php if ($heading !== '') : ?>
                    <p class="polski-consent-banner__heading"><strong><?php echo esc_html($heading); ?></strong></p>
                <?php endif; ?>
                <p class="polski-consent-banner__text"><?php echo wp_kses_post((string) $settings['banner_text']); ?></p>

                <div class="polski-consent-banner__categories" hidden>
                    <label class="polski-consent-banner__category">
                        <input type="checkbox" checked disabled value="necessary">
                        <?php echo esc_html(ConsentCategory::Necessary->label()); ?>
                    </label>
                    <?php foreach ($categories as $category) :
                        $enum = ConsentCategory::tryFrom($category);
                        if ($enum === null) {
                            continue;
                        }
                        ?>
                        <label class="polski-consent-banner__category">
                            <input type="checkbox" data-polski-consent-category value="<?php echo esc_attr($category); ?>">
                            <?php echo esc_html($enum->label()); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="polski-consent-banner__actions">
                <button type="button" class="polski-consent-banner__btn" data-polski-consent-action="manage">
                    <?php echo esc_html((string) $settings['manage_label']); ?>
                </button>
                <button type="button" class="polski-consent-banner__btn" data-polski-consent-action="reject">
                    <?php echo esc_html((string) $settings['reject_label']); ?>
                </button>
                <button type="button" class="polski-consent-banner__btn polski-consent-banner__btn--save" data-polski-consent-action="save" hidden>
                    <?php echo esc_html((string) $settings['save_label']); ?>
                </button>
                <button type="button" class="polski-consent-banner__btn polski-consent-banner__btn--accept" data-polski-consent-action="accept">
                    <?php echo esc_html((string) $settings['accept_label']); ?>
                </button>
            </div>
        </div>
        <?php
    }
}
