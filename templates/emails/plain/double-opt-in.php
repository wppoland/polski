<?php
/**
 * Double Opt-In activation email (plain text).
 *
 * @var string  $activation_url
 * @var int     $user_id
 * @var string  $email_heading
 * @var string  $additional_content
 *
 * @package Spolszczony/Templates/Emails
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$user = get_user_by('id', $user_id);
$name = $user ? $user->display_name : '';

echo "= " . wp_strip_all_tags($email_heading) . " =\n\n";
printf(esc_html__('Hello %s,', 'spolszczony') . "\n\n", esc_html($name));
echo esc_html__('Thank you for creating an account. Please visit the following link to activate your account:', 'spolszczony') . "\n\n";
echo esc_url($activation_url) . "\n\n";

if ($additional_content) {
    echo wp_strip_all_tags($additional_content) . "\n";
}
