<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;

/**
 * Double Opt-In for customer registration.
 *
 * When enabled, new customers must verify their email address before
 * they can log in. An activation link is sent via email.
 */
final class DoubleOptInService implements Bootable, HasHooks
{
    private bool $enabled = false;
    private int $cleanupDays = 7;

    public function boot(): void
    {
        $settings = get_option('spolszczony_doi', []);
        $this->enabled = is_array($settings) && (bool) ($settings['enabled'] ?? false);
        $this->cleanupDays = is_array($settings) ? (int) ($settings['cleanup_days'] ?? 7) : 7;
    }

    public function registerHooks(): void
    {
        if (! $this->enabled) {
            return;
        }

        // On registration, set user as unactivated and send email.
        add_action('woocommerce_created_customer', [$this, 'onCustomerCreated'], 10, 3);

        // Block login for unactivated accounts.
        add_filter('wp_authenticate_user', [$this, 'blockUnactivatedLogin'], 10, 2);

        // Handle activation link.
        add_action('template_redirect', [$this, 'handleActivation']);

        // Cleanup old unactivated accounts.
        add_action('spolszczony_daily_maintenance', [$this, 'cleanupUnactivated']);
    }

    /**
     * Mark new customer as unactivated and send activation email.
     */
    public function onCustomerCreated(int $customerId, array $newCustomerData, bool $passwordGenerated): void
    {
        $token = wp_generate_password(32, false);

        update_user_meta($customerId, '_spolszczony_doi_token', $token);
        update_user_meta($customerId, '_spolszczony_doi_activated', 'no');
        update_user_meta($customerId, '_spolszczony_doi_created', time());

        $activationUrl = add_query_arg([
            'spolszczony_doi' => $customerId,
            'token' => $token,
        ], wc_get_page_permalink('myaccount'));

        $user = get_user_by('id', $customerId);
        $email = $user ? $user->user_email : '';

        /**
         * Fires to trigger the DOI activation email.
         *
         * @param int    $customerId
         * @param string $email
         * @param string $activationUrl
         */
        do_action('spolszczony/doi/email_sent', $customerId, $email, $activationUrl);
    }

    /**
     * Block login for unactivated accounts.
     */
    public function blockUnactivatedLogin(\WP_User|\WP_Error $user, string $password): \WP_User|\WP_Error
    {
        if ($user instanceof \WP_Error) {
            return $user;
        }

        $activated = get_user_meta($user->ID, '_spolszczony_doi_activated', true);

        if ($activated === 'no') {
            return new \WP_Error(
                'spolszczony_doi_not_activated',
                __('Your account has not been activated yet. Please check your email for the activation link.', 'spolszczony'),
            );
        }

        return $user;
    }

    /**
     * Handle activation link click.
     */
    public function handleActivation(): void
    {
        if (! isset($_GET['spolszczony_doi'], $_GET['token'])) {
            return;
        }

        $userId = (int) $_GET['spolszczony_doi'];
        $token = sanitize_text_field(wp_unslash($_GET['token']));

        $storedToken = get_user_meta($userId, '_spolszczony_doi_token', true);

        if (! hash_equals((string) $storedToken, $token)) {
            wc_add_notice(__('Invalid activation link.', 'spolszczony'), 'error');
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        update_user_meta($userId, '_spolszczony_doi_activated', 'yes');
        delete_user_meta($userId, '_spolszczony_doi_token');

        /**
         * Fires after a customer's account is activated via DOI.
         *
         * @param int $userId
         */
        do_action('spolszczony/doi/confirmed', $userId);

        wc_add_notice(__('Your account has been activated. You can now log in.', 'spolszczony'), 'success');
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    /**
     * Cleanup unactivated accounts older than the configured period.
     */
    public function cleanupUnactivated(): void
    {
        $cutoff = time() - ($this->cleanupDays * DAY_IN_SECONDS);

        $users = get_users([
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_spolszczony_doi_activated',
                    'value' => 'no',
                ],
                [
                    'key' => '_spolszczony_doi_created',
                    'value' => $cutoff,
                    'compare' => '<',
                    'type' => 'NUMERIC',
                ],
            ],
            'fields' => 'ids',
        ]);

        foreach ($users as $userId) {
            // Only delete if user has no orders.
            $orderCount = wc_get_customer_order_count((int) $userId);

            if ($orderCount === 0) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user((int) $userId);
            }
        }
    }
}
