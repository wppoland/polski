<?php
defined('ABSPATH') || exit;
/**
 * Double Opt-In activation email (HTML).
 *
 * @var string  $activation_url
 * @var int     $user_id
 * @var string  $email_heading
 * @var string  $additional_content
 * @var WC_Email $email
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
do_action('woocommerce_email_header', $email_heading, $email);

$user = get_user_by('id', $user_id);
$name = $user ? $user->display_name : '';
$settings = get_option('polski_doi', []);
$settings = is_array($settings) ? $settings : [];
$greeting = str_replace('{name}', $name, (string) ($settings['email_greeting'] ?? __('Cześć {name},', 'polski')));
?>

<p><?php echo esc_html($greeting); ?></p>

<p><?php echo esc_html((string) ($settings['email_intro_html'] ?? __('Dziękujemy za założenie konta. Kliknij przycisk poniżej, aby aktywować konto:', 'polski'))); ?></p>

<p style="text-align:center;margin:30px 0;">
    <a href="<?php echo esc_url($activation_url); ?>" style="background-color:#7f54b3;color:#ffffff;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;">
        <?php echo esc_html((string) ($settings['email_button_text'] ?? __('Aktywuj konto', 'polski'))); ?>
    </a>
</p>

<p><?php echo esc_html((string) ($settings['email_link_intro'] ?? __('Jeśli wolisz, skopiuj i wklej ten link do przeglądarki:', 'polski'))); ?></p>
<p><a href="<?php echo esc_url($activation_url); ?>"><?php echo esc_html($activation_url); ?></a></p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php do_action('woocommerce_email_footer', $email); ?>
