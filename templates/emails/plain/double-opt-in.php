<?php
/**
 * Double Opt-In activation email (plain text).
 *
 * @var string  $polski_activation_url
 * @var int     $polski_user_id
 * @var string  $polski_email_heading
 * @var string  $polski_additional_content
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

$user = get_user_by('id', $user_id);
$name = $user instanceof \WP_User ? $user->display_name : '';
$settings = get_option('polski_doi', []);
$settings = is_array($settings) ? $settings : [];
$greeting = str_replace('{name}', $name, (string) ($settings['email_greeting'] ?? __('Cześć {name},', 'polski')));

echo "= " . esc_html(wp_strip_all_tags($email_heading)) . " =\n\n";
echo esc_html($greeting) . "\n\n";
echo esc_html((string) ($settings['email_intro_plain'] ?? __('Dziękujemy za założenie konta. Odwiedź poniższy link, aby aktywować konto:', 'polski'))) . "\n\n";
echo esc_url($activation_url) . "\n\n";

if ($additional_content) {
    echo esc_html(wp_strip_all_tags($additional_content)) . "\n";
}
