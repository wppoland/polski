<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;
use Polski\Enum\ConsentCategory;
use Polski\Service\ConsentManagerService;

/**
 * Tracking Tags: a unified, consent-gated tag manager for common marketing and
 * analytics providers.
 *
 * The merchant turns on the providers they use and enters their own tracking
 * ID or domain in the module settings. Nothing is hardcoded and the plugin
 * never makes any outbound HTTP request from PHP; every tag is a small,
 * client-side snippet emitted into the page.
 *
 * Crucially, every tag is routed through ConsentManagerService so it is wrapped
 * in a `<script type="text/plain" data-polski-consent="CATEGORY">` element and
 * only executes once the visitor has granted the matching consent category:
 *  - ad / remarketing pixels gate under `marketing`,
 *  - measurement / heatmap tools gate under `analytics`.
 *
 * GA4 and Google Tag Manager are intentionally NOT handled here; they live in
 * DataLayerService together with the WooCommerce ecommerce events.
 *
 * These are tools that help store owners load third-party tags responsibly;
 * they do not by themselves guarantee any particular legal outcome.
 */
final class TrackingTagsService implements HasHooks
{
    public const OPTION = 'polski_tracking_tags';

    /**
     * Provider registry. Each entry declares the settings key holding the
     * merchant ID/domain and the consent category the tag gates under.
     *
     * `id_key` is the field inside the option array; `enable_key` is the
     * per-provider on/off toggle. `category` is a ConsentCategory value.
     *
     * @var array<string, array{label: string, id_key: string, enable_key: string, category: string, id_hint: string}>
     */
    private const PROVIDERS = [
        'meta_pixel' => [
            'label' => 'Meta Pixel',
            'id_key' => 'meta_pixel_id',
            'enable_key' => 'meta_pixel_enabled',
            'category' => 'marketing',
            'id_hint' => 'Pixel ID',
        ],
        'tiktok' => [
            'label' => 'TikTok Pixel',
            'id_key' => 'tiktok_id',
            'enable_key' => 'tiktok_enabled',
            'category' => 'marketing',
            'id_hint' => 'Pixel ID',
        ],
        'microsoft_ads' => [
            'label' => 'Microsoft Advertising (Bing UET)',
            'id_key' => 'microsoft_ads_id',
            'enable_key' => 'microsoft_ads_enabled',
            'category' => 'marketing',
            'id_hint' => 'UET Tag ID',
        ],
        'clarity' => [
            'label' => 'Microsoft Clarity',
            'id_key' => 'clarity_id',
            'enable_key' => 'clarity_enabled',
            'category' => 'analytics',
            'id_hint' => 'Project ID',
        ],
        'linkedin' => [
            'label' => 'LinkedIn Insight',
            'id_key' => 'linkedin_id',
            'enable_key' => 'linkedin_enabled',
            'category' => 'marketing',
            'id_hint' => 'Partner ID',
        ],
        'pinterest' => [
            'label' => 'Pinterest Tag',
            'id_key' => 'pinterest_id',
            'enable_key' => 'pinterest_enabled',
            'category' => 'marketing',
            'id_hint' => 'Tag ID',
        ],
        'matomo' => [
            'label' => 'Matomo',
            'id_key' => 'matomo_site_id',
            'enable_key' => 'matomo_enabled',
            'category' => 'analytics',
            'id_hint' => 'Site ID',
        ],
        'plausible' => [
            'label' => 'Plausible',
            'id_key' => 'plausible_domain',
            'enable_key' => 'plausible_enabled',
            'category' => 'analytics',
            'id_hint' => 'example.com',
        ],
        'posthog' => [
            'label' => 'PostHog',
            'id_key' => 'posthog_key',
            'enable_key' => 'posthog_enabled',
            'category' => 'analytics',
            'id_hint' => 'Project API key',
        ],
        'twitter' => [
            'label' => 'X / Twitter Ads',
            'id_key' => 'twitter_id',
            'enable_key' => 'twitter_enabled',
            'category' => 'marketing',
            'id_hint' => 'Pixel ID',
        ],
        'google_ads' => [
            'label' => 'Google Ads',
            'id_key' => 'google_ads_id',
            'enable_key' => 'google_ads_enabled',
            'category' => 'marketing',
            'id_hint' => 'AW-XXXXXXXXX',
        ],
        'hotjar' => [
            'label' => 'Hotjar',
            'id_key' => 'hotjar_id',
            'enable_key' => 'hotjar_enabled',
            'category' => 'analytics',
            'id_hint' => 'Site ID',
        ],
        'inspectlet' => [
            'label' => 'Inspectlet',
            'id_key' => 'inspectlet_id',
            'enable_key' => 'inspectlet_enabled',
            'category' => 'analytics',
            'id_hint' => 'WID',
        ],
        'crazy_egg' => [
            'label' => 'Crazy Egg',
            'id_key' => 'crazy_egg_id',
            'enable_key' => 'crazy_egg_enabled',
            'category' => 'analytics',
            'id_hint' => 'Account ID',
        ],
        'simple_analytics' => [
            'label' => 'Simple Analytics',
            'id_key' => 'simple_analytics_enabled_marker',
            'enable_key' => 'simple_analytics_enabled',
            'category' => 'analytics',
            'id_hint' => '',
        ],
    ];

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('tracking_tags')) {
            return;
        }

        // Tags print after Consent Mode (wp_head @0) and DataLayer (wp_head @1).
        add_action('wp_head', [$this, 'printHeadTags'], 20);

        // Providers that prefer the body close (e.g. Hotjar's queue init).
        add_action('wp_footer', [$this, 'printFooterTags'], 20);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $defaults = [];

        foreach (self::PROVIDERS as $provider) {
            $defaults[$provider['enable_key']] = false;
            $defaults[$provider['id_key']] = '';
        }

        $defaults['matomo_url'] = '';

        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        return wp_parse_args($saved, $defaults);
    }

    /**
     * Print all head-loaded provider tags, each consent-gated.
     */
    public function printHeadTags(): void
    {
        if (is_admin()) {
            return;
        }

        foreach (array_keys(self::PROVIDERS) as $providerKey) {
            // Hotjar initialises better just before </body>.
            if ($providerKey === 'hotjar') {
                continue;
            }

            $tag = $this->buildTag($providerKey);

            if ($tag !== '') {
                // The snippet is already escaped/gated by ConsentManagerService.
                echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }
    }

    /**
     * Print footer-loaded provider tags, each consent-gated.
     */
    public function printFooterTags(): void
    {
        if (is_admin()) {
            return;
        }

        $tag = $this->buildTag('hotjar');

        if ($tag !== '') {
            echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Build a single provider's consent-gated tag, or an empty string when the
     * provider is disabled or its ID is not configured.
     */
    public function buildTag(string $providerKey): string
    {
        if (! isset(self::PROVIDERS[$providerKey])) {
            return '';
        }

        $provider = self::PROVIDERS[$providerKey];
        $settings = $this->getSettings();

        if (empty($settings[$provider['enable_key']])) {
            return '';
        }

        $id = isset($settings[$provider['id_key']]) ? trim((string) $settings[$provider['id_key']]) : '';

        // Simple Analytics has no ID; the enable toggle alone is enough. Every
        // other provider needs a configured ID/domain before it can emit a tag.
        $needsId = $provider['id_key'] !== 'simple_analytics_enabled_marker';

        if ($id === '' && $needsId) {
            return '';
        }

        $category = $provider['category'];

        return match ($providerKey) {
            'meta_pixel' => $this->metaPixel($id, $category),
            'tiktok' => $this->tiktok($id, $category),
            'microsoft_ads' => $this->microsoftAds($id, $category),
            'clarity' => $this->clarity($id, $category),
            'linkedin' => $this->linkedin($id, $category),
            'pinterest' => $this->pinterest($id, $category),
            'matomo' => $this->matomo($id, (string) ($settings['matomo_url'] ?? ''), $category),
            'plausible' => $this->plausible($id, $category),
            'posthog' => $this->posthog($id, $category),
            'twitter' => $this->twitter($id, $category),
            'google_ads' => $this->googleAds($id, $category),
            'hotjar' => $this->hotjar($id, $category),
            'inspectlet' => $this->inspectlet($id, $category),
            'crazy_egg' => $this->crazyEgg($id, $category),
            default => $this->simpleAnalytics($category),
        };
    }

    /**
     * Expose the provider registry for documentation / admin display.
     *
     * @return array<string, array{label: string, id_key: string, enable_key: string, category: string, id_hint: string}>
     */
    public static function providers(): array
    {
        return self::PROVIDERS;
    }

    // ── Tag builders ─────────────────────────────────────────────────────
    // Each returns markup wrapped by ConsentManagerService so it only runs
    // after the visitor grants the relevant category.

    private function metaPixel(string $id, string $category): string
    {
        $js = "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?"
            . "n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;"
            . "n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;"
            . "t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}"
            . "(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');"
            . 'fbq("init",' . wp_json_encode($id) . ');fbq("track","PageView");';

        return ConsentManagerService::gateScript($category, $js);
    }

    private function tiktok(string $id, string $category): string
    {
        $js = "!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];"
            . "ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'];"
            . "ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};"
            . "for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);"
            . "ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};"
            . "ttq.load=function(e,n){var r='https://analytics.tiktok.com/i18n/pixel/events.js';"
            . "ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e]=+new Date;"
            . "ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=d.createElement('script');o.type='text/javascript';o.async=!0;"
            . "o.src=r+'?sdkid='+e+'&lib='+t;var a=d.getElementsByTagName('script')[0];a.parentNode.insertBefore(o,a)};"
            . 'ttq.load(' . wp_json_encode($id) . ');ttq.page();}(window,document,"ttq");';

        return ConsentManagerService::gateScript($category, $js);
    }

    private function microsoftAds(string $id, string $category): string
    {
        $js = "(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:" . wp_json_encode($id) . ",enableAutoSpaTracking:!0};"
            . "o.q=w[u],w[u]=new UET(o),w[u].push('pageLoad')},n=d.createElement(t),n.src=r,n.async=1,"
            . "n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=='loaded'&&s!=='complete'||(f(),n.onload=n.onreadystatechange=null)},"
            . "i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,'script','https://bat.bing.com/bat.js','uetq');";

        return ConsentManagerService::gateScript($category, $js);
    }

    private function clarity(string $id, string $category): string
    {
        $js = "(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};"
            . "t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;"
            . "y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);}"
            . '(window,document,"clarity","script",' . wp_json_encode($id) . ');';

        return ConsentManagerService::gateScript($category, $js);
    }

    private function linkedin(string $id, string $category): string
    {
        $js = '_linkedin_partner_id=' . wp_json_encode($id) . ';'
            . "window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];"
            . "window._linkedin_data_partner_ids.push(_linkedin_partner_id);"
            . "(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}"
            . "var s=document.getElementsByTagName('script')[0];var b=document.createElement('script');"
            . "b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';"
            . "s.parentNode.insertBefore(b,s);})(window.lintrk);";

        return ConsentManagerService::gateScript($category, $js);
    }

    private function pinterest(string $id, string $category): string
    {
        $js = "!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};"
            . "var n=window.pintrk;n.queue=[],n.version='3.0';var t=document.createElement('script');t.async=!0,t.src=e;"
            . "var r=document.getElementsByTagName('script')[0];r.parentNode.insertBefore(t,r)}}('https://s.pinimg.com/ct/core.js');"
            . 'pintrk("load",' . wp_json_encode($id) . ');pintrk("page");';

        return ConsentManagerService::gateScript($category, $js);
    }

    private function matomo(string $siteId, string $url, string $category): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $url = trailingslashit(esc_url_raw($url));

        $js = "var _paq=window._paq=window._paq||[];_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);"
            . "(function(){var u=" . wp_json_encode($url) . ";"
            . "_paq.push(['setTrackerUrl',u+'matomo.php']);_paq.push(['setSiteId'," . wp_json_encode($siteId) . "]);"
            . "var d=document,g=d.createElement('script'),s=d.getElementsByTagName('script')[0];"
            . "g.async=true;g.src=u+'matomo.js';s.parentNode.insertBefore(g,s);})();";

        return ConsentManagerService::gateScript($category, $js);
    }

    private function plausible(string $domain, string $category): string
    {
        $src = 'https://plausible.io/js/script.js';
        $tag = sprintf(
            '<script type="text/plain" data-polski-consent="%s" data-src="%s" data-domain="%s" defer></script>',
            esc_attr($category),
            esc_url($src),
            esc_attr($domain),
        );

        return $tag;
    }

    private function posthog(string $key, string $category): string
    {
        $js = "!function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){"
            . "function g(t,e){var o=e.split('.');2==o.length&&(t=t[o[0]],e=o[1]);t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}"
            . "(p=t.createElement('script')).type='text/javascript',p.async=!0,p.src=s.api_host+'/static/array.js',"
            . "(r=t.getElementsByTagName('script')[0]).parentNode.insertBefore(p,r);var u=e;"
            . "for(void 0!==a?u=e[a]=[]:a='posthog',u.people=u.people||[],u.toString=function(t){var e='posthog';return'posthog'!==a&&(e+='.'+a),t||(e+=' (stub)'),e},"
            . "u.people.toString=function(){return u.toString(1)+'.people (stub)'},"
            . "o='capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId'.split(' '),n=0;n<o.length;n++)g(u,o[n]);"
            . "e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);"
            . 'posthog.init(' . wp_json_encode($key) . ',{api_host:"https://app.posthog.com"});';

        return ConsentManagerService::gateScript($category, $js);
    }

    private function twitter(string $id, string $category): string
    {
        $js = "!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments)},"
            . "s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',"
            . "a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');"
            . 'twq("config",' . wp_json_encode($id) . ');';

        return ConsentManagerService::gateScript($category, $js);
    }

    private function googleAds(string $id, string $category): string
    {
        $src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($id);

        $loader = ConsentManagerService::gateSrc($category, $src);

        $js = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . 'gtag("js",new Date());gtag("config",' . wp_json_encode($id) . ');';

        return $loader . ConsentManagerService::gateScript($category, $js);
    }

    private function hotjar(string $id, string $category): string
    {
        $js = "(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};"
            . "h._hjSettings={hjid:" . wp_json_encode($id) . ",hjsv:6};a=o.getElementsByTagName('head')[0];"
            . "r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;"
            . "a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');";

        return ConsentManagerService::gateScript($category, $js);
    }

    private function inspectlet(string $id, string $category): string
    {
        $js = "window.__insp=window.__insp||[];__insp.push(['wid'," . wp_json_encode($id) . "]);"
            . "(function(){function ldinsp(){if(typeof window.__inspld!='undefined')return;window.__inspld=1;"
            . "var insp=document.createElement('script');insp.type='text/javascript';insp.async=true;insp.id='inspsync';"
            . "insp.src='https://cdn.inspectlet.com/inspectlet.js?wid='+encodeURIComponent(" . wp_json_encode($id) . ")+'&r='+Math.floor(new Date().getTime()/3600000);"
            . "var x=document.getElementsByTagName('script')[0];x.parentNode.insertBefore(insp,x)}"
            . "setTimeout(ldinsp,0)})();";

        return ConsentManagerService::gateScript($category, $js);
    }

    private function crazyEgg(string $id, string $category): string
    {
        $id = preg_replace('/[^0-9]/', '', $id) ?? '';

        if (strlen($id) < 8) {
            return '';
        }

        $path = substr($id, 0, 4) . '/' . substr($id, 4, 4);
        $src = 'https://script.crazyegg.com/pages/scripts/' . $path . '.js';

        return ConsentManagerService::gateSrc($category, $src);
    }

    private function simpleAnalytics(string $category): string
    {
        $src = 'https://scripts.simpleanalyticscdn.com/latest.js';

        return sprintf(
            '<script type="text/plain" data-polski-consent="%s" data-src="%s" async defer></script>',
            esc_attr($category),
            esc_url($src),
        );
    }
}
