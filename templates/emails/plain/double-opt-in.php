<?php
/**
 * Double Opt-In activation email (plain text).
 *
 * @var string  $activation_url
 * @var int     $user_id
 * @var string  $email_heading
 * @var string  $additional_content
 *
 * @package Polski/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$user = get_user_by('id', $user_id);
$name = $user ? $user->display_name : '';
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
