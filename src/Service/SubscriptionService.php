<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Model\Subscription;
use Polski\Repository\SubscriptionRepository;
use Polski\Util\Formatter;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Manual-renewal subscription engine for recurring products.
 */
final class SubscriptionService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_subscriptions';
    private const ENDPOINT = 'polski-subscriptions';

    public function __construct(
        private readonly SubscriptionRepository $repository,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerEndpoint']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderSubscriptionBox'], 6);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addCartItemData'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyInitialPricing'], 21);
        add_filter('woocommerce_get_item_data', [$this, 'renderItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addOrderItemMeta'], 10, 4);
        add_action('woocommerce_order_status_processing', [$this, 'activateSubscriptionsFromOrder']);
        add_action('woocommerce_order_status_completed', [$this, 'activateSubscriptionsFromOrder']);
        add_action('polski_daily_maintenance', [$this, 'sendRenewalReminders']);
        add_action('polski_daily_maintenance', [$this, 'processRenewals']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'renderAccountPage']);
        add_action('wp_loaded', [$this, 'handleAccountActions']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('subscriptions');
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        wp_enqueue_style(
            'polski-subscriptions',
            \Polski\Plugin::instance()->url('assets/css/subscriptions.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function renderSubscriptionBox(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->isSubscriptionProduct($product) || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $this->templateLoader->include('single-product/subscription-box', [
            'service' => $this,
            'config' => $this->getProductConfig($product),
        ]);
    }

    /**
     * @param array<string, mixed> $cartItemData
     * @return array<string, mixed>
     */
    public function addCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $product = wc_get_product($variationId > 0 ? $variationId : $productId);

        if (! $product instanceof \WC_Product || ! $this->isSubscriptionProduct($product)) {
            return $cartItemData;
        }

        $config = $this->getProductConfig($product);
        $cartItemData['polski_subscription'] = $config;
        $cartItemData['polski_subscription_base_price'] = (float) $product->get_price('edit');

        return $cartItemData;
    }

    public function applyInitialPricing(\WC_Cart $cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $item) {
            if (empty($item['polski_subscription']) || ! isset($item['data']) || ! $item['data'] instanceof \WC_Product) {
                continue;
            }

            $config = is_array($item['polski_subscription']) ? $item['polski_subscription'] : [];
            $basePrice = (float) ($item['polski_subscription_base_price'] ?? 0);
            $signupFee = (float) ($config['signup_fee'] ?? 0);
            $trialDays = (int) ($config['trial_days'] ?? 0);
            $initialPrice = $trialDays > 0 ? $signupFee : $basePrice + $signupFee;
            $item['data']->set_price(max(0.0, $initialPrice));
        }
    }

    /**
     * @param list<array{name: string, value: string}> $itemData
     * @param array<string, mixed>                     $cartItem
     * @return list<array{name: string, value: string}>
     */
    public function renderItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['polski_subscription']) || ! is_array($cartItem['polski_subscription'])) {
            return $itemData;
        }

        $config = $cartItem['polski_subscription'];
        $itemData[] = [
            'name' => (string) ($this->getSettings()['subscription_label'] ?? __('Subskrypcja', 'polski')),
            'value' => $this->getHumanInterval((int) ($config['interval_count'] ?? 1), (string) ($config['interval_period'] ?? 'month')),
        ];

        if ((int) ($config['trial_days'] ?? 0) > 0) {
            $itemData[] = [
                'name' => (string) ($this->getSettings()['status_trial'] ?? __('Okres próbny', 'polski')),
                'value' => $this->getTrialDurationText((int) $config['trial_days']),
            ];
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function addOrderItemMeta(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        if (empty($values['polski_subscription']) || ! is_array($values['polski_subscription'])) {
            return;
        }

        $config = $values['polski_subscription'];
        $item->add_meta_data('_polski_subscription', wp_json_encode($config), true);
        $item->add_meta_data(
            (string) ($this->getSettings()['subscription_label'] ?? __('Subskrypcja', 'polski')),
            $this->getHumanInterval((int) ($config['interval_count'] ?? 1), (string) ($config['interval_period'] ?? 'month')),
            true,
        );
    }

    public function activateSubscriptionsFromOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();

            if (! $product instanceof \WC_Product || ! $this->isSubscriptionProduct($product)) {
                continue;
            }

            if ($this->repository->findByOrderAndProduct($orderId, $product->get_id()) !== null) {
                continue;
            }

            $config = json_decode((string) $item->get_meta('_polski_subscription', true), true);

            if (! is_array($config)) {
                $config = $this->getProductConfig($product);
            }

            $nextPaymentAt = $this->calculateNextPaymentDate(
                new \DateTimeImmutable('now', wp_timezone()),
                (int) ($config['trial_days'] ?? 0) > 0 ? (int) ($config['trial_days'] ?? 0) : null,
                (int) ($config['interval_count'] ?? 1),
                (string) ($config['interval_period'] ?? 'month'),
            );

            $status = (int) ($config['trial_days'] ?? 0) > 0 ? 'trial' : 'active';

            $this->repository->create([
                'user_id' => $order->get_user_id(),
                'email' => (string) $order->get_billing_email(),
                'product_id' => $product->get_id(),
                'product_name' => $product->get_name(),
                'source_order_id' => $orderId,
                'quantity' => (int) $item->get_quantity(),
                'status' => $status,
                'interval_count' => (int) ($config['interval_count'] ?? 1),
                'interval_period' => (string) ($config['interval_period'] ?? 'month'),
                'cycles_total' => (int) ($config['cycles_total'] ?? 0),
                'cycles_completed' => 0,
                'recurring_amount' => (float) ($config['recurring_amount'] ?? $product->get_price('edit')),
                'signup_fee' => (float) ($config['signup_fee'] ?? 0),
                'trial_days' => (int) ($config['trial_days'] ?? 0),
                'next_payment_at' => $nextPaymentAt->format('Y-m-d H:i:s'),
                'last_payment_at' => current_time('mysql', true),
            ]);
        }
    }

    public function sendRenewalReminders(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $days = max(1, (int) ($this->getSettings()['reminder_days'] ?? 3));
        $threshold = (new \DateTimeImmutable('now', wp_timezone()))->modify('+' . $days . ' days')->format('Y-m-d H:i:s');

        foreach ($this->repository->findUpcomingReminders($threshold, $days) as $subscription) {
            if ($subscription->nextPaymentAt === null) {
                continue;
            }

            $subject = Formatter::interpolate(
                (string) ($this->getSettings()['reminder_subject'] ?? 'Zbliża się odnowienie subskrypcji - {product_name}'),
                ['product_name' => $subscription->productName],
            );

            $message = sprintf(
                "%s\n\n%s\n%s",
                str_replace(
                    ['{product_name}', '{date}'],
                    [$subscription->productName, wp_date($this->getDateFormat(), $subscription->nextPaymentAt->getTimestamp())],
                    (string) ($this->getSettings()['reminder_intro_text'] ?? __('Subskrypcja produktu {product_name} odnowi się {date}.', 'polski')),
                ),
                str_replace(
                    '{amount}',
                    Formatter::price($subscription->recurringAmount * $subscription->quantity),
                    (string) ($this->getSettings()['reminder_amount_label'] ?? __('Kwota odnowienia: {amount}', 'polski')),
                ),
                wc_get_page_permalink('myaccount'),
            );

            if (wp_mail($subscription->email, $subject, $message)) {
                $this->repository->markReminderSent($subscription->id);
            }
        }
    }

    public function processRenewals(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $now = current_time('mysql', true);

        foreach ($this->repository->findDueRenewals($now) as $subscription) {
            if ($subscription->status === 'cancelled' || $subscription->nextPaymentAt === null) {
                continue;
            }

            if ($subscription->cyclesTotal > 0 && $subscription->cyclesCompleted >= $subscription->cyclesTotal) {
                $this->repository->updateStatus($subscription->id, 'completed');
                continue;
            }

            $product = wc_get_product($subscription->productId);

            if (! $product instanceof \WC_Product) {
                continue;
            }

            $order = wc_create_order([
                'customer_id' => $subscription->userId ?? 0,
            ]);

            if ($order instanceof \WP_Error) {
                continue;
            }

            $order->add_product($product, $subscription->quantity);
            $order->set_address([
                'email' => $subscription->email,
            ], 'billing');
            $order->calculate_totals(false);
            $order->set_total($subscription->recurringAmount * $subscription->quantity);
            $order->add_order_note(sprintf(__('Automatycznie utworzono zamówienie odnowieniowe dla subskrypcji #%d.', 'polski'), $subscription->id));
            $order->save();

            $cyclesCompleted = $subscription->cyclesCompleted + 1;
            $nextPaymentAt = $this->calculateNextPaymentDate(
                $subscription->nextPaymentAt,
                null,
                $subscription->intervalCount,
                $subscription->intervalPeriod,
            );

            $status = ($subscription->cyclesTotal > 0 && $cyclesCompleted >= $subscription->cyclesTotal) ? 'completed' : 'active';
            $this->repository->markRenewed($subscription->id, $nextPaymentAt->format('Y-m-d H:i:s'), $status, $cyclesCompleted);

            $subject = Formatter::interpolate(
                (string) ($this->getSettings()['renewal_subject'] ?? 'Nowe odnowienie subskrypcji - {product_name}'),
                ['product_name' => $subscription->productName],
            );

            $message = sprintf(
                "%s\n\n%s\n%s",
                str_replace(
                    '{product_name}',
                    $subscription->productName,
                    (string) ($this->getSettings()['renewal_intro_text'] ?? __('Utworzyliśmy nowe zamówienie odnowieniowe dla subskrypcji produktu {product_name}.', 'polski')),
                ),
                str_replace(
                    '{amount}',
                    Formatter::price($subscription->recurringAmount * $subscription->quantity),
                    (string) ($this->getSettings()['renewal_amount_label'] ?? __('Kwota do opłacenia: {amount}', 'polski')),
                ),
                $order->get_checkout_payment_url(),
            );

            wp_mail($subscription->email, $subject, $message);
        }
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function addAccountMenuItem(array $items): array
    {
        if (! $this->isEnabled() || ! ($this->getSettings()['show_in_account'] ?? true)) {
            return $items;
        }

        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);

        $items[self::ENDPOINT] = (string) ($this->getSettings()['account_label'] ?? __('Subskrypcje', 'polski'));

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public function renderAccountPage(): void
    {
        $customer = wp_get_current_user();
        $subscriptions = $customer instanceof \WP_User ? $this->repository->findForAccount((int) $customer->ID, (string) $customer->user_email) : [];

        $this->templateLoader->include('account/subscriptions', [
            'service' => $this,
            'subscriptions' => $subscriptions,
        ]);
    }

    public function handleAccountActions(): void
    {
        if (! $this->isEnabled() || ! isset($_GET['polski_subscription_action'], $_GET['subscription_id'])) {
            return;
        }

        $action = sanitize_key((string) wp_unslash($_GET['polski_subscription_action']));
        $subscriptionId = (int) wp_unslash($_GET['subscription_id']);
        $nonce = (string) wp_unslash($_GET['_wpnonce'] ?? '');

        if (! wp_verify_nonce($nonce, 'polski_subscription_' . $action . '_' . $subscriptionId)) {
            return;
        }

        $subscription = $this->repository->findById($subscriptionId);

        if ($subscription === null || ((int) get_current_user_id() > 0 && $subscription->userId !== (int) get_current_user_id())) {
            return;
        }

        if ($action === 'cancel' && ($this->getSettings()['allow_cancellation'] ?? true)) {
            $this->repository->updateStatus($subscriptionId, 'cancelled');
            wc_add_notice((string) ($this->getSettings()['cancel_success_text'] ?? __('Subskrypcja została anulowana.', 'polski')), 'success');
        } elseif ($action === 'reactivate' && $subscription->status === 'cancelled') {
            $this->repository->updateStatus($subscriptionId, 'active');
            wc_add_notice((string) ($this->getSettings()['reactivate_success_text'] ?? __('Subskrypcja została ponownie aktywowana.', 'polski')), 'success');
        }
    }

    public function renderSubscriptionsList(): string
    {
        $customer = wp_get_current_user();
        $subscriptions = $customer instanceof \WP_User ? $this->repository->findForAccount((int) $customer->ID, (string) $customer->user_email) : [];

        return $this->templateLoader->render('account/subscriptions', [
            'service' => $this,
            'subscriptions' => $subscriptions,
        ]);
    }

    public function isSubscriptionProduct(\WC_Product $product): bool
    {
        return $this->isEnabled() && $product->get_meta('_polski_subscription_enabled', true) === 'yes';
    }

    /**
     * @return array<string, mixed>
     */
    public function getProductConfig(\WC_Product $product): array
    {
        return [
            'interval_count' => max(1, (int) $product->get_meta('_polski_subscription_interval', true)),
            'interval_period' => (string) ($product->get_meta('_polski_subscription_period', true) ?: 'month'),
            'cycles_total' => max(0, (int) $product->get_meta('_polski_subscription_length', true)),
            'signup_fee' => max(0.0, (float) $product->get_meta('_polski_subscription_signup_fee', true)),
            'trial_days' => max(0, (int) $product->get_meta('_polski_subscription_trial_days', true)),
            'recurring_amount' => (float) $product->get_price('edit'),
        ];
    }

    public function getHumanInterval(int $intervalCount, string $period): string
    {
        $template = (string) ($this->getSettings()['subscription_cycle_format'] ?? __('co {count} {period}', 'polski'));

        return str_replace(
            ['{count}', '{period}'],
            [(string) $intervalCount, $this->getPeriodLabel($period, $intervalCount)],
            $template,
        );
    }

    public function getStatusLabel(Subscription $subscription): string
    {
        return match ($subscription->status) {
            'trial' => (string) ($this->getSettings()['status_trial'] ?? __('Okres próbny', 'polski')),
            'cancelled' => (string) ($this->getSettings()['status_cancelled'] ?? __('Anulowana', 'polski')),
            'completed' => (string) ($this->getSettings()['status_completed'] ?? __('Zakończona', 'polski')),
            default => (string) ($this->getSettings()['status_active'] ?? __('Aktywna', 'polski')),
        };
    }

    public function getSignupFeeText(float $amount): string
    {
        $template = (string) ($this->getSettings()['signup_fee_label'] ?? __('Opłata startowa: {price}', 'polski'));

        return str_replace('{price}', wp_strip_all_tags(wc_price($amount)), $template);
    }

    public function getTrialText(int $days): string
    {
        $template = (string) ($this->getSettings()['trial_label'] ?? __('Okres próbny: {duration}', 'polski'));

        return str_replace(
            ['{duration}', '{days}'],
            [$this->getTrialDurationText($days), (string) $days],
            $template,
        );
    }

    public function getDateFormat(): string
    {
        return (string) ($this->getSettings()['date_format'] ?? 'd.m.Y');
    }

    private function getTrialDurationText(int $days): string
    {
        $template = (string) ($this->getSettings()['trial_value_format'] ?? '{days} {period}');

        return str_replace(
            ['{days}', '{period}'],
            [(string) $days, $this->getPeriodLabel('day', $days)],
            $template,
        );
    }

    private function getPeriodLabel(string $period, int $count): string
    {
        $settings = $this->getSettings();
        $isSingular = $count === 1;

        return match ($period) {
            'day' => (string) ($settings[$isSingular ? 'period_day_singular' : 'period_day_plural'] ?? ($isSingular ? __('dzień', 'polski') : __('dni', 'polski'))),
            'week' => (string) ($settings[$isSingular ? 'period_week_singular' : 'period_week_plural'] ?? ($isSingular ? __('tydzień', 'polski') : __('tygodnie', 'polski'))),
            'month' => (string) ($settings[$isSingular ? 'period_month_singular' : 'period_month_plural'] ?? ($isSingular ? __('miesiąc', 'polski') : __('miesiące', 'polski'))),
            'year' => (string) ($settings[$isSingular ? 'period_year_singular' : 'period_year_plural'] ?? ($isSingular ? __('rok', 'polski') : __('lata', 'polski'))),
            default => $period,
        };
    }

    private function calculateNextPaymentDate(\DateTimeImmutable $from, ?int $trialDays, int $intervalCount, string $period): \DateTimeImmutable
    {
        if ($trialDays !== null && $trialDays > 0) {
            return $from->modify('+' . $trialDays . ' days');
        }

        return match ($period) {
            'day' => $from->modify('+' . $intervalCount . ' days'),
            'week' => $from->modify('+' . $intervalCount . ' weeks'),
            'year' => $from->modify('+' . $intervalCount . ' years'),
            default => $from->modify('+' . $intervalCount . ' months'),
        };
    }
}
