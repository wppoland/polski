<?php
/**
 * Double Opt-In activation email (HTML).
 *
 * @var string  $activation_url
 * @var int     $user_id
 * @var string  $email_heading
 * @var string  $additional_content
 * @var WC_Email $email
 *
 * @package Spolszczony/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email);

$user = get_user_by('id', $user_id);
$name = $user ? $user->display_name : '';
?>

<p><?php printf(esc_html__('Hello %s,', 'spolszczony'), esc_html($name)); ?></p>

<p><?php esc_html_e('Thank you for creating an account. Please click the button below to activate your account:', 'spolszczony'); ?></p>

<p style="text-align:center;margin:30px 0;">
    <a href="<?php echo esc_url($activation_url); ?>" style="background-color:#7f54b3;color:#ffffff;padding:12px 30px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;">
        <?php esc_html_e('Activate Account', 'spolszczony'); ?>
    </a>
</p>

<p><?php esc_html_e('Or copy and paste this link into your browser:', 'spolszczony'); ?></p>
<p><a href="<?php echo esc_url($activation_url); ?>"><?php echo esc_html($activation_url); ?></a></p>

<?php if ($additional_content) : ?>
    <p><?php echo wp_kses_post($additional_content); ?></p>
<?php endif; ?>

<?php do_action('woocommerce_email_footer', $email); ?>
