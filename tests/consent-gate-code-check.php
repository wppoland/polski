<?php

declare(strict_types=1);

/**
 * Consent Manager: pasted tracking code and known tracker scripts wait for consent.
 *
 *   php tests/consent-gate-code-check.php
 */

define('ABSPATH', __DIR__);
$root = dirname(__DIR__) . '/';

function esc_attr(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function esc_url(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function wp_parse_url(string $url, int $component = -1): mixed { return parse_url($url, $component); }

eval('namespace Polski\Contract; interface HasHooks { public function registerHooks(): void; }');
eval('namespace Polski\Util; trait SettingsCacheable { public array $s = []; public function getSettings(): array { return $this->s; } }');
eval('namespace Polski\Admin; class ModulesPage { public static function isModuleEnabled(string $id): bool { return true; } }');
eval('namespace Polski\Enum; enum ConsentCategory: string { case Analytics = "analytics"; case Marketing = "marketing"; case Preferences = "preferences"; public static function optional(): array { return self::cases(); } }');
eval('namespace Polski; const PLUGIN_DIR = ""; const PLUGIN_FILE = ""; const VERSION = "test";');
require $root . 'src/Service/ConsentManagerService.php';

use Polski\Service\ConsentManagerService;

$failures = 0;
$checks = 0;
function check(string $label, bool $ok): void
{
    global $failures, $checks;
    $checks++;
    echo ($ok ? 'ok    ' : 'FAIL  ') . $label . "\n";
    if (! $ok) {
        $failures++;
    }
}

$pixel = "<!-- Meta Pixel -->\n<script>\n!function(f,b,e,v,n,t,s){fbq('init','123');}(window);\n</script>\n<noscript><img height=\"1\" width=\"1\" src=\"https://www.facebook.com/tr?id=123&ev=PageView\"/></noscript>";
$out = ConsentManagerService::gateCode('marketing', $pixel);
check('an inline pasted script becomes text/plain for its category', str_contains($out, '<script type="text/plain" data-polski-consent="marketing">') && str_contains($out, "fbq('init','123')"));
check('the noscript pixel is left out', ! str_contains($out, 'facebook.com/tr'));

$ga = '<script async src="https://www.googletagmanager.com/gtag/js?id=G-X"></script><script>gtag("config","G-X");</script>';
$out = ConsentManagerService::gateCode('analytics', $ga);
check('a src script keeps its URL as data-src', str_contains($out, 'data-polski-consent="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-X"'));
check('nothing runs before consent', substr_count($out, 'type="text/plain"') === 2 && ! preg_match('#<script(?![^>]*text/plain)#', $out));
check('a closing tag in a snippet cannot break out', ! str_contains(ConsentManagerService::gateScript('analytics', 'a="</script><script>x()"'), '</script><script>x()'));

$svc = new ConsentManagerService();
$svc->s = ['category_analytics' => true, 'category_marketing' => true, 'google_consent_mode' => true];
$tag = "<script src='https://connect.facebook.net/en_US/fbevents.js' id='pixel-js'></script>\n";
check('an enqueued Meta Pixel waits for marketing', str_contains($svc->gateKnownTracker($tag, 'pixel', 'https://connect.facebook.net/en_US/fbevents.js'), 'data-polski-consent="marketing"'));
$gtm = "<script src='https://www.googletagmanager.com/gtm.js?id=GTM-X'></script>\n";
check('with Consent Mode, Google Tag Manager loads as is', $svc->gateKnownTracker($gtm, 'gtm', 'https://www.googletagmanager.com/gtm.js?id=GTM-X') === $gtm);
$svc2 = new ConsentManagerService();
$svc2->s = ['category_analytics' => true, 'google_consent_mode' => false];
check('without Consent Mode, Google Tag Manager waits for analytics', str_contains($svc2->gateKnownTracker($gtm, 'gtm', 'https://www.googletagmanager.com/gtm.js?id=GTM-X'), 'data-polski-consent="analytics"'));
$own = "<script src='https://shop.test/wp-content/themes/x/app.js'></script>\n";
check('other scripts are untouched', $svc->gateKnownTracker($own, 'app', 'https://shop.test/wp-content/themes/x/app.js') === $own);
$svc3 = new ConsentManagerService();
$svc3->s = ['category_analytics' => true, 'google_consent_mode' => true];
check('a tracker whose category is switched off is left alone', $svc3->gateKnownTracker($tag, 'pixel', 'https://connect.facebook.net/en_US/fbevents.js') === $tag);

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures > 0 ? 1 : 0);
