<?php
/**
 * Double Opt-In activation email (HTML).
 *
 * @var string  $polski_activation_url
 * @var int     $polski_user_id
 * @var string  $polski_email_heading
 * @var string  $polski_additional_content
 * @var WC_Email $polski_email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook for template integration.
do_action('woocommerce_email_header', $polski_email_heading, $polski_email);

$polski_user = get_user_by('id', $polski_user_id);
$polski_name = $polski_user ? $polski_user->display_name : '';
$polski_settings = get_option('polski_doi', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_greeting = str_replace('{name}', $polski_name, (string) ($polski_settings['email_greeting'] ?? __('Cześć {name},', 'polski')));
?>

<p><?php echo esc_html($polski_greeting); ?></p>

<p><?php echo esc_html((string) ($polski_settings['email_intro_html'] ?? __('Dziękujemy za założenie konta. Kliknij przycisk poniżej, aby aktywować konto:', 'polski'))); ?></p>

<p style="text-align:center;margin:30px 0;">
    <a href="<?php echo esc_url($polski_activation_url); ?>" style="background-color:#7f54b3;color:#ffffff;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;">
        <?php echo esc_html((string) ($polski_settings['email_button_text'] ?? __('Aktywuj konto', 'polski'))); ?>
    </a>
</p>

<p><?php echo esc_html((string) ($polski_settings['email_link_intro'] ?? __('Jeśli wolisz, skopiuj i wklej ten link do przeglądarki:', 'polski'))); ?></p>
<p><a href="<?php echo esc_url($polski_activation_url); ?>"><?php echo esc_html($polski_activation_url); ?></a></p>

<?php if ($polski_additional_content) : ?>
    <p><?php echo wp_kses_post($polski_additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $polski_email);
?>
