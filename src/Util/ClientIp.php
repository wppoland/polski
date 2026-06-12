<?php

declare(strict_types=1);

namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * Trusted-proxy-aware client IP resolver.
 *
 * By default only REMOTE_ADDR is trusted. Forwarding headers
 * (X-Forwarded-For / CF-Connecting-IP) are fully attacker-controlled on a
 * plain install, so they are honored only when the site explicitly opts into
 * a known reverse proxy via the `polski/trusted_proxy` filter. This prevents
 * spoofed headers from defeating IP-keyed rate limits or forging audit-log IPs.
 */
final class ClientIp
{
    public static function resolve(): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR'])
            ? trim(sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])))
            : '';

        if (apply_filters('polski/trusted_proxy', false)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                if (empty($_SERVER[$key])) {
                    continue;
                }
                $value = sanitize_text_field((string) wp_unslash($_SERVER[$key]));
                $value = trim(explode(',', $value)[0] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return $remote !== '' ? $remote : '0.0.0.0';
    }
}
