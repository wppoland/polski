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
$polski_user = get_user_by('id', $polski_user_id);
$polski_name = $polski_user ? $polski_user->display_name : '';
$polski_settings = get_option('polski_doi', []);
$polski_settings = is_array($polski_settings) ? $polski_settings : [];
$polski_greeting = str_replace('{name}', $polski_name, (string) ($polski_settings['email_greeting'] ?? __('Cześć {name},', 'polski')));

echo "= " . esc_html(wp_strip_all_tags($polski_email_heading)) . " =\n\n";
echo esc_html($polski_greeting) . "\n\n";
echo esc_html((string) ($polski_settings['email_intro_plain'] ?? __('Dziękujemy za założenie konta. Odwiedź poniższy link, aby aktywować konto:', 'polski'))) . "\n\n";
echo esc_url($polski_activation_url) . "\n\n";

if ($polski_additional_content) {
    echo esc_html(wp_strip_all_tags($polski_additional_content)) . "\n";
}
