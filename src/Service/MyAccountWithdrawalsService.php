<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;
use Polski\Repository\WithdrawalRepository;

/**
 * Adds a dedicated "Withdrawals" tab to the WooCommerce My Account area so
 * the customer can review the history of their declarations in one place.
 *
 * The endpoint slug can be overridden via the `polski_withdrawal` option
 * (`my_account_endpoint_slug`).
 */
final class MyAccountWithdrawalsService implements HasHooks
{
    public const DEFAULT_SLUG = 'polski-withdrawals';
    private const MENU_PRIORITY = 30;

    public function __construct(
        private readonly WithdrawalRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerEndpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'addMenuItem'], 25);
        add_filter('woocommerce_get_query_vars', [$this, 'addQueryVar']);
        add_action('woocommerce_account_' . $this->endpointSlug() . '_endpoint', [$this, 'renderEndpoint']);
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint($this->endpointSlug(), EP_ROOT | EP_PAGES);
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function addMenuItem(array $items): array
    {
        $injected = [];
        $slug = $this->endpointSlug();
        $label = __('Moje odstąpienia', 'polski');

        foreach ($items as $key => $value) {
            $injected[$key] = $value;

            if ($key === 'orders') {
                $injected[$slug] = $label;
            }
        }

        if (! isset($injected[$slug])) {
            $injected[$slug] = $label;
        }

        return $injected;
    }

    /**
     * @param array<string, string> $vars
     * @return array<string, string>
     */
    public function addQueryVar(array $vars): array
    {
        $slug = $this->endpointSlug();
        $vars[$slug] = $slug;

        return $vars;
    }

    public function renderEndpoint(): void
    {
        $customerId = get_current_user_id();

        if ($customerId <= 0) {
            esc_html_e('Aby zobaczyć swoje odstąpienia musisz być zalogowany.', 'polski');
            return;
        }

        $requests = $this->repository->findByCustomer($customerId);
        ?>
        <h2><?php esc_html_e('Moje odstąpienia', 'polski'); ?></h2>

        <?php if ($requests === []) : ?>
            <p><?php esc_html_e('Nie złożyłeś jeszcze żadnego wniosku o odstąpienie od umowy.', 'polski'); ?></p>
        <?php else : ?>
            <table class="shop_table polski-my-account-withdrawals">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Numer', 'polski'); ?></th>
                        <th><?php esc_html_e('Zamówienie', 'polski'); ?></th>
                        <th><?php esc_html_e('Data', 'polski'); ?></th>
                        <th><?php esc_html_e('Status', 'polski'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $request) : ?>
                    <tr>
                        <td><?php echo esc_html(sprintf('POL-WD-%06d', $request->id)); ?></td>
                        <td>
                            <?php
                            $order = wc_get_order($request->orderId);
                            $orderNumber = $order instanceof \WC_Order ? $order->get_order_number() : (string) $request->orderId;
                            $orderUrl = $order instanceof \WC_Order ? $order->get_view_order_url() : '';

                            if ($orderUrl !== '') {
                                printf(
                                    '<a href="%s">#%s</a>',
                                    esc_url($orderUrl),
                                    esc_html($orderNumber),
                                );
                            } else {
                                echo '#' . esc_html($orderNumber);
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($request->requestedAt->format(get_option('date_format') . ' H:i')); ?></td>
                        <td><?php echo esc_html($request->status->label()); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    private function endpointSlug(): string
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $slug = sanitize_title((string) ($settings['my_account_endpoint_slug'] ?? ''));

        return $slug !== '' ? $slug : self::DEFAULT_SLUG;
    }
}
