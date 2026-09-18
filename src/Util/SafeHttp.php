<?php

declare(strict_types=1);
namespace Polski\Util;

defined('ABSPATH') || exit;

/**
 * One retry for calls that are safe to repeat.
 *
 * Every external call in this plugin goes to a public register: GUS, VIES, a
 * shop's own page, an OAuth userinfo endpoint. Those are reads, they time out
 * from time to time, and until now a single timeout was reported to the
 * customer as "no data found for that VAT ID", which is not what happened.
 *
 * Only reads go through here. A retried write can charge twice, post a webhook
 * twice or burn a single-use OAuth code, so those call wp_remote_* directly and
 * are meant to.
 */
final class SafeHttp
{
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    public static function get(string $url, array $args = []): array|\WP_Error
    {
        return self::request('wp_remote_get', $url, $args);
    }

    /**
     * POST is only safe when the endpoint treats it as a query, which is how
     * the GUS SOAP service works. Never use this for a call that changes
     * something on the other side.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    public static function post(string $url, array $args = []): array|\WP_Error
    {
        return self::request('wp_remote_post', $url, $args);
    }

    /**
     * @param callable-string $fn
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    private static function request(string $fn, string $url, array $args): array|\WP_Error
    {
        $response = $fn($url, $args);

        if (! self::isTransientFailure($response)) {
            return $response;
        }

        /**
         * Pause before the single retry, in microseconds.
         *
         * An immediate retry usually hits the same dropped connection, and this
         * runs inside a customer's request, so the wait stays small enough not
         * to be felt. Filter it to 0 to retry at once.
         *
         * @param int $microseconds Default 200 ms.
         */
        $delay = (int) apply_filters('polski/http/retry_delay', 200000);

        if ($delay > 0) {
            usleep($delay);
        }

        $retry = $fn($url, $args);

        // A failed retry returns the SECOND answer, not the first: if the
        // service came back with a real error the second time, that error is
        // the more useful one to report.
        return $retry;
    }

    /**
     * True for the failures that are worth repeating: no answer at all, or the
     * server saying it broke. A 4xx is an answer, and repeating it only wastes
     * the customer's time; 429 in particular means "slow down".
     *
     * @param array<string, mixed>|\WP_Error $response
     */
    public static function isTransientFailure(array|\WP_Error $response): bool
    {
        if (is_wp_error($response)) {
            return true;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 500 && $code <= 599;
    }
}
