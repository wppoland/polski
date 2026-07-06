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
 * Safe Fonts: reduce and gate external Google Fonts requests.
 *
 * Two independent, opt-in behaviours, applied to any enqueued stylesheet whose
 * URL points at fonts.googleapis.com:
 *
 *  (a) Optimise: append `display=swap` to the Google Fonts URL when missing and
 *      emit `preconnect` resource hints for fonts.googleapis.com and the
 *      crossorigin fonts.gstatic.com host. This trims layout shift and the
 *      connection cost without changing what is loaded.
 *
 *  (b) Gate until consent: rewrite the Google Fonts <link> into a deferred
 *      placeholder (a disabled <link> carrying the real href in a data
 *      attribute). A tiny vanilla controller re-enables it once the visitor has
 *      granted the configured consent category, and on the Consent Manager's
 *      `polskiConsentChange` event. Until then the external request is not made.
 *
 * Graceful fallback: if a tag cannot be parsed or rewritten it is returned
 * untouched, so fonts always keep working. True self-hosting of the font files
 * is out of scope for v1; this module reduces and gates the external calls.
 *
 * These are tools that help store owners cut and defer third-party font
 * requests; they do not by themselves guarantee any particular legal outcome.
 */
final class SafeFontsService implements HasHooks
{
    public const OPTION = 'polski_safe_fonts';

    private const GOOGLE_FONTS_API = 'fonts.googleapis.com';
    private const GOOGLE_FONTS_STATIC = 'fonts.gstatic.com';

    /** Whether at least one Google Fonts link was gated on this request. */
    private bool $gatedAny = false;

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('safe_fonts')) {
            return;
        }

        $settings = $this->getSettings();

        if (! empty($settings['optimize'])) {
            add_filter('style_loader_src', [$this, 'addFontDisplay'], 20, 2);
            add_action('wp_head', [$this, 'printPreconnect'], 1);
        }

        if (! empty($settings['gate_until_consent'])) {
            add_filter('style_loader_tag', [$this, 'gateFontTag'], 20, 4);
            add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueController']);
        }
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
                'optimize' => true,
                'gate_until_consent' => false,
                'consent_category' => ConsentCategory::Preferences->value,
            ],
        );
    }

    private function consentCategory(): string
    {
        $category = (string) ($this->getSettings()['consent_category'] ?? ConsentCategory::Preferences->value);

        return ConsentCategory::tryFrom($category) !== null
            ? $category
            : ConsentCategory::Preferences->value;
    }

    private static function isGoogleFontsUrl(string $url): bool
    {
        return stripos($url, self::GOOGLE_FONTS_API) !== false;
    }

    // ── (a) Optimise ─────────────────────────────────────────────────────

    /**
     * Append `display=swap` to a Google Fonts stylesheet URL when it lacks an
     * explicit display value. Non-font URLs are returned unchanged.
     *
     * @param string $src    Stylesheet URL.
     * @param string $handle Style handle (unused, kept for the filter signature).
     */
    public function addFontDisplay(string $src, string $handle): string
    {
        unset($handle);

        if (! self::isGoogleFontsUrl($src)) {
            return $src;
        }

        if (stripos($src, 'display=') !== false) {
            return $src;
        }

        $separator = strpos($src, '?') === false ? '?' : '&';

        return $src . $separator . 'display=swap';
    }

    /**
     * Emit preconnect hints for the Google Fonts hosts. Cheap and safe to print
     * even when no font ends up loading.
     */
    public function printPreconnect(): void
    {
        if (is_admin()) {
            return;
        }

        printf(
            '<link rel="preconnect" href="%s">' . "\n",
            esc_url('https://' . self::GOOGLE_FONTS_API),
        );
        printf(
            '<link rel="preconnect" href="%s" crossorigin>' . "\n",
            esc_url('https://' . self::GOOGLE_FONTS_STATIC),
        );
    }

    // ── (b) Gate until consent ───────────────────────────────────────────

    /**
     * Rewrite a Google Fonts <link> into a deferred placeholder that a small
     * controller re-enables once consent is granted. Anything that is not a
     * parseable Google Fonts stylesheet tag is returned untouched (graceful).
     *
     * @param string $tag    The full <link> HTML WordPress generated.
     * @param string $handle Style handle (unused, kept for the filter signature).
     * @param string $href   Resolved stylesheet URL.
     * @param string $media  Media attribute (unused, kept for the signature).
     */
    public function gateFontTag(string $tag, string $handle, string $href, string $media): string
    {
        unset($handle, $media);

        if (is_admin() || ! self::isGoogleFontsUrl($href)) {
            return $tag;
        }

        // Only rewrite a standard stylesheet link we can safely reconstruct.
        if (stripos($tag, 'rel=') === false || stripos($tag, 'stylesheet') === false) {
            return $tag;
        }

        $this->gatedAny = true;

        // A disabled stylesheet link does not fetch; the controller flips it on.
        $replacement = sprintf(
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- rewrites an already-enqueued style_loader_tag into an inert disabled link, re-enabled by JS after consent; cannot be re-enqueued.
            '<link rel="stylesheet" href="about:blank" data-polski-safefont="%1$s" data-polski-consent="%2$s" media="print" disabled>',
            esc_url($href),
            esc_attr($this->consentCategory()),
        );

        // Preserve a no-JS fallback so fonts still load when scripting is off.
        $noscript = '<noscript>' . $tag . '</noscript>';

        return $replacement . $noscript;
    }

    /**
     * Enqueue the tiny controller that re-enables gated font links on consent.
     * Only loaded when a font was actually gated on this request.
     */
    public function maybeEnqueueController(): void
    {
        if (is_admin()) {
            return;
        }

        // style_loader_tag runs while building the head; enqueue happens earlier,
        // so register the script and only print it from the footer if needed.
        $deps = [];
        $version = VERSION;

        $assetFile = PLUGIN_DIR . '/build/frontend-safefonts.asset.php';
        if (is_readable($assetFile)) {
            /** @var array{dependencies?: list<string>, version?: string} $asset */
            $asset = require $assetFile;
            $deps = is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
            $version = isset($asset['version']) ? (string) $asset['version'] : VERSION;
        }

        wp_register_script(
            'polski-safe-fonts',
            plugins_url('build/frontend-safefonts.js', PLUGIN_FILE),
            $deps,
            $version,
            true,
        );

        wp_localize_script('polski-safe-fonts', 'polskiSafeFonts', [
            'event' => ConsentManagerService::EVENT,
            'cookie' => ConsentManagerService::COOKIE,
        ]);

        // Enqueue in the footer once we know whether any font was gated.
        add_action('wp_footer', [$this, 'enqueueIfGated'], 1);
    }

    public function enqueueIfGated(): void
    {
        if ($this->gatedAny) {
            wp_enqueue_script('polski-safe-fonts');
        }
    }
}
