<?php

declare(strict_types=1);

/**
 * A site that never ran the activation hook (a network subsite, a site created
 * later) must still get config/defaults.php, and the texts it falls back to
 * must be the English source strings from that file, not a second copy.
 *
 * Before this check the activation hook was the only seeder, so a subsite
 * showed "Reject all" where defaults.php says "Reject optional", and the VAT
 * and delivery-time labels fell back to Polish literals in every locale.
 *
 *   php tests/fresh-site-defaults-check.php
 */

namespace Polski {
    define('ABSPATH', sys_get_temp_dir() . '/');
    const PLUGIN_DIR = __DIR__ . '/..';
    const VERSION = '9.9.9';

    // The real Migrator touches the database; a fresh site has nothing to migrate here.
    final class Migrator
    {
        public function runPending(): void
        {
        }
    }
}

namespace {
    $GLOBALS['opts'] = [];

    function __($text, $domain = null)
    {
        return $text;
    }
    function esc_html__($text, $domain = null)
    {
        return $text;
    }
    function get_option($name, $default = false)
    {
        return array_key_exists($name, $GLOBALS['opts']) ? $GLOBALS['opts'][$name] : $default;
    }
    function update_option($name, $value)
    {
        $GLOBALS['opts'][$name] = $value;
        return true;
    }
    function wp_parse_args($args, $defaults = [])
    {
        return array_merge($defaults, $args);
    }

    class WC_Email
    {
    }

    spl_autoload_register(static function (string $class): void {
        if (strncmp($class, 'Polski\\', 7) === 0) {
            $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });

    $fail = 0;
    $check = static function (string $label, bool $ok) use (&$fail): void {
        echo ($ok ? 'ok   ' : 'FAIL ') . $label . "\n";
        $fail |= $ok ? 0 : 1;
    };
    $call = static function (object $obj, string $method, array $args = []) {
        $m = new ReflectionMethod($obj, $method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    };
    $defaults = require __DIR__ . '/../config/defaults.php';

    // 1. First boot stores no translated defaults: texts resolve at read time in
    // the request's language, so a subsite must not freeze whichever language
    // its first visitor or cron run happened to use.
    $GLOBALS['opts'] = ['polski_taxes' => ['vat_notice_text' => 'saved {rate}']];
    $plugin = (new ReflectionClass(\Polski\Plugin::class))->newInstanceWithoutConstructor();
    $call($plugin, 'syncInstalledVersion');
    $check('first boot stores no consent texts', ! isset($GLOBALS['opts']['polski_consent_manager']));
    $check('first boot keeps a saved option', $GLOBALS['opts']['polski_taxes'] === ['vat_notice_text' => 'saved {rate}']);
    $check('first boot records the version', ($GLOBALS['opts']['polski_version'] ?? null) === \Polski\VERSION);

    // 3. Unseeded reads fall back to defaults.php, the English source strings.
    $GLOBALS['opts'] = [];
    $consent = (new \Polski\Service\ConsentManagerService())->getSettings();
    $check('consent reject label comes from defaults.php', $consent['reject_label'] === $defaults['polski_consent_manager']['reject_label']);
    $check('consent banner text comes from defaults.php', $consent['banner_text'] === $defaults['polski_consent_manager']['banner_text']);
    $check('consent embeds blocked by default', $consent['block_embeds'] === true);
    $tax = $call(new \Polski\Service\TaxDisplayService(), 'getSettings');
    $check('VAT label falls back to the English source', ($tax['vat_notice_text'] ?? null) === 'incl. {rate}% VAT');
    $delivery = new \Polski\Service\DeliveryTimeService();
    $check(
        'delivery label falls back to the English source',
        method_exists($delivery, 'getSettings') && ($call($delivery, 'getSettings')['display_format'] ?? null) === 'Delivery time: {time}',
    );
    $email = (new ReflectionClass(\Polski\Email\WithdrawalConfirmationEmail::class))->newInstanceWithoutConstructor();
    $check('withdrawal email subject comes from defaults.php', $email->get_default_subject() === $defaults['polski_withdrawal']['email_subject']);

    // 4. A saved value still wins.
    $GLOBALS['opts'] = [
        'polski_consent_manager' => ['reject_label' => 'Nein'],
        'polski_withdrawal' => ['email_subject' => 'Mine'],
    ];
    $check('saved consent label wins', (new \Polski\Service\ConsentManagerService())->getSettings()['reject_label'] === 'Nein');
    $check('saved email subject wins', $email->get_default_subject() === 'Mine');

    // 5. The modules form shows the same consent texts as defaults.php.
    $form = (string) file_get_contents(__DIR__ . '/../src/Admin/ModulesPage.php');
    foreach (['banner_text', 'reject_label'] as $key) {
        $check(
            "modules form default for consent {$key} matches defaults.php",
            str_contains($form, "'polski_consent_manager|{$key}'") && preg_match(
                "/'polski_consent_manager\\|{$key}'[^\\n]*'default' => __\\('" . preg_quote($defaults['polski_consent_manager'][$key], '/') . "'/",
                $form,
            ) === 1,
        );
    }

    echo $fail ? "FAILED\n" : "PASSED\n";
    exit($fail);
}
