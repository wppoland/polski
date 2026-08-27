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

$activation_url = $activation_url ?? $polski_activation_url ?? '';
$user_id = (int) ($user_id ?? $polski_user_id ?? 0);
$email_heading = $email_heading ?? $polski_email_heading ?? '';
$additional_content = $additional_content ?? $polski_additional_content ?? '';
$email = $email ?? $polski_email ?? null;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email header hook for template integration.
do_action('woocommerce_email_header', $email_heading, $email);

$user = get_user_by('id', $user_id);
$name = $user instanceof \WP_User ? $user->display_name : '';
$settings = get_option('polski_doi', []);
$settings = is_array($settings) ? $settings : [];
$greeting = str_replace('{name}', $name, (string) ($settings['email_greeting'] ?? __('Hi {name},', 'polski')));
?>

<p><?php echo esc_html($greeting); ?></p>

<p><?php echo esc_html((string) ($settings['email_intro_html'] ?? __('Thank you for creating an account. Click the button below to activate it:', 'polski'))); ?></p>

<p style="text-align:center;margin:30px 0;">
    <a href="<?php echo esc_url($activation_url); ?>" style="background-color:#7f54b3;color:#ffffff;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;">
        <?php echo esc_html((string) ($settings['email_button_text'] ?? __('Activate the account', 'polski'))); ?>
    </a>
</p>

<p><?php echo esc_html((string) ($settings['email_link_intro'] ?? __('If you prefer, copy this link into your browser:', 'polski'))); ?></p>
<p><a href="<?php echo esc_url($activation_url); ?>"><?php echo esc_html($activation_url); ?></a></p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking WooCommerce core email footer hook for template integration.
do_action('woocommerce_email_footer', $email);
?>
