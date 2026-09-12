<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;

/**
 * Double Opt-In for customer registration.
 *
 * When enabled, new customers must verify their email address before
 * they can log in. An activation link is sent via email.
 */
final class DoubleOptInService implements Bootable, HasHooks
{
    /**
     * How many unactivated accounts the cleanup cron loads per query.
     */
    private const CLEANUP_BATCH_SIZE = 200;

    private bool $enabled = false;
    private int $cleanupDays = 7;
    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    public function boot(): void
    {
        $settings = get_option('polski_doi', []);
        $this->settings = is_array($settings) ? $settings : [];
        // Both switches have to agree. Guarding on the feature setting alone
        // left the Modules toggle decorative, which is the family-wide bug this
        // release clears up. Unlike the food module, this setting really is
        // written by the admin screen, so it stays in the condition rather than
        // being replaced. Migration_2_7_0 switches the module on wherever the
        // setting is already true, so no store suddenly starts letting
        // unverified accounts log in.
        $this->enabled = \Polski\Admin\ModulesPage::isModuleEnabled('double_opt_in')
            && (bool) ($this->settings['enabled'] ?? false);
        $this->cleanupDays = (int) ($this->settings['cleanup_days'] ?? 7);
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
        add_action('polski_daily_maintenance', [$this, 'cleanupUnactivated']);
    }

    /**
     * Mark new customer as unactivated and send activation email.
     */
    /**
     * @param array<string, mixed> $newCustomerData
     */
    public function onCustomerCreated(int $customerId, array $newCustomerData, bool $passwordGenerated): void
    {
        $token = wp_generate_password(32, false);

        update_user_meta($customerId, '_polski_doi_token', $token);
        update_user_meta($customerId, '_polski_doi_activated', 'no');
        update_user_meta($customerId, '_polski_doi_created', time());

        $activationUrl = add_query_arg([
            'polski_doi' => $customerId,
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
        do_action('polski/doi/email_sent', $customerId, $email, $activationUrl);
    }

    /**
     * Block login for unactivated accounts.
     */
    public function blockUnactivatedLogin(\WP_User|\WP_Error $user, string $password): \WP_User|\WP_Error
    {
        if ($user instanceof \WP_Error) {
            return $user;
        }

        $activated = get_user_meta($user->ID, '_polski_doi_activated', true);

        if ($activated === 'no') {
            return new \WP_Error(
                'polski_doi_not_activated',
                (string) ($this->settings['login_blocked_text'] ?? __('Your account is awaiting activation! Please check your email and click the activation link.', 'polski')),
            );
        }

        return $user;
    }

    /**
     * Handle activation link click.
     */
    public function handleActivation(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verification is handled by the activation token itself.
        if (! isset($_GET['polski_doi'], $_GET['token'])) {
            return;
        }

        $userId = absint(wp_unslash($_GET['polski_doi']));
        $token = sanitize_text_field((string) wp_unslash($_GET['token']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $storedToken = get_user_meta($userId, '_polski_doi_token', true);

        if (! hash_equals((string) $storedToken, $token)) {
            wc_add_notice((string) ($this->settings['invalid_link_text'] ?? __('Invalid activation link.', 'polski')), 'error');
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }

        update_user_meta($userId, '_polski_doi_activated', 'yes');
        delete_user_meta($userId, '_polski_doi_token');

        /**
         * Fires after a customer's account is activated via DOI.
         *
         * @param int $userId
         */
        do_action('polski/doi/confirmed', $userId);

        wc_add_notice((string) ($this->settings['activation_success_text'] ?? __('Great! Your account is now activated. You can now log in.', 'polski')), 'success');
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }

    /**
     * Cleanup unactivated accounts older than the configured period.
     */
    public function cleanupUnactivated(): void
    {
        $cutoff = time() - ($this->cleanupDays * DAY_IN_SECONDS);

        /**
         * Number of unactivated accounts examined per query.
         *
         * @param int $batchSize
         */
        $batchSize = (int) apply_filters('polski/doi/cleanup_batch_size', self::CLEANUP_BATCH_SIZE);

        if ($batchSize < 1) {
            $batchSize = self::CLEANUP_BATCH_SIZE;
        }

        // No offset and no page counter: both count positions in a result set
        // that this loop is itself shrinking. Instead every account the batch
        // does not remove is excluded from the next query, so each pass either
        // deletes rows or excludes them, and the candidate set strictly shrinks.
        // That also bounds the loop when wp_delete_user() refuses, which an
        // offset that only advanced on the keep branch did not.
        $keep = [];

        // ponytail: one cron run still walks the whole backlog, just in slices.
        // If a store ever has more stale accounts than a single cron run can
        // chew through, move this loop to Action Scheduler and handle one batch
        // per scheduled action.
        do {
            $users = get_users([
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for cleanup cron: find unactivated accounts older than cutoff.
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_polski_doi_activated',
                        'value' => 'no',
                    ],
                    [
                        'key' => '_polski_doi_created',
                        'value' => $cutoff,
                        'compare' => '<',
                        'type' => 'NUMERIC',
                    ],
                ],
                'fields' => 'ids',
                'number' => $batchSize,
                'exclude' => $keep,
            ]);

            $users = is_array($users) ? $users : [];

            foreach ($users as $userId) {
                // Only delete if user has no orders.
                $orderCount = wc_get_customer_order_count((int) $userId);

                if ($orderCount === 0) {
                    if (! function_exists('wp_delete_user')) {
                        require_once ABSPATH . 'wp-admin/includes/user.php';
                    }

                    if (wp_delete_user((int) $userId)) {
                        continue;
                    }
                }

                // Has orders, or the delete was refused. Either way this account
                // is still in the result set, so the next query must skip it.
                $keep[] = (int) $userId;
            }
        } while (count($users) === $batchSize);
    }
}
