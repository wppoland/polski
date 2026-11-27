<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

final class KSeFReadyService implements HasHooks
{
    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('woocommerce_checkout_order_processed', [$this, 'detectKSeFRequired'], 10, 1);
        // Block checkout (the WooCommerce default) never fires the classic hook.
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'detectKSeFRequired'], 20, 1);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'renderKSeFStatus']);
        add_action('polski/order/tax_id_changed', [$this, 'detectKSeFRequired']);
        add_filter('manage_edit-shop_order_columns', [$this, 'addOrderColumn'], 20);
        add_action('manage_shop_order_posts_custom_column', [$this, 'renderOrderColumn'], 10, 2);
        // HPOS support.
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'addOrderColumn'], 20);
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'renderHPOSOrderColumn'], 10, 2);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('ksef_ready');
    }

    /**
     * Detect whether the order requires a KSeF invoice based on NIP presence.
     */
    public function detectKSeFRequired(int|\WC_Order $orderId): void
    {
        $order = $orderId instanceof \WC_Order ? $orderId : wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        // Check for NIP in billing data from supported checkout fields.
        $nip = $order->get_meta('_billing_nip', true);

        if (empty($nip)) {
            $nip = $order->get_meta('_polski_billing_nip', true);
        }

        $required = match ($this->invoiceTrigger()) {
            // Every order gets an invoice in KSeF, consumers included.
            'all' => true,
            // A VAT ID, or the customer ticked "I need a VAT invoice".
            'request' => ! empty($nip) || $this->invoiceRequested($order),
            default => $this->autoDetectNip() && ! empty($nip),
        };

        /** @var bool $required Filterable KSeF requirement. */
        $required = (bool) apply_filters('polski/ksef/is_required', $required, $order);

        $order->update_meta_data('_polski_ksef_required', $required ? 'yes' : 'no');
        $order->save();

        if ($required) {
            /** Fires when an order is ready for KSeF invoicing. */
            do_action('polski/ksef/invoice_ready', $order);
        }
    }

    /** Which orders need a KSeF invoice: 'nip' (a VAT ID, default), 'request' or 'all'. */
    private function invoiceTrigger(): string
    {
        $settings = get_option('polski_ksef', []);
        $trigger = is_array($settings) ? (string) ($settings['invoice_trigger'] ?? 'nip') : 'nip';

        return in_array($trigger, ['nip', 'request', 'all'], true) ? $trigger : 'nip';
    }

    private function invoiceRequested(\WC_Order $order): bool
    {
        foreach (['_polski_needs_invoice', '_wc_other/polski/needs_invoice'] as $key) {
            $value = $order->get_meta($key, true);
            if ($value === 'yes' || $value === '1' || $value === true || $value === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a VAT ID on the order should mark it as needing KSeF.
     *
     * Switching this off does not disable the module: the admin column and the
     * status panel stay, and an integration can still flag an order through the
     * `polski/ksef/is_required` filter. That is what keeps this from being a
     * duplicate of the module toggle.
     *
     * A missing key means true, which is what the code did before this was
     * wired up and matches the default the modules screen declares.
     */
    private function autoDetectNip(): bool
    {
        $settings = get_option('polski_ksef', []);

        if (! is_array($settings) || ! array_key_exists('auto_detect_nip', $settings)) {
            return true;
        }

        return (bool) $settings['auto_detect_nip'];
    }

    /** KSeF relief (art. 145m ustawy o VAT): invoices outside KSeF while the month stays at or under this gross amount, until the end of 2026. */
    public const KSEF_MONTHLY_LIMIT = 10000.0;

    /** Cash register exemption (§ 3 rozporządzenia MF, Dz.U. 2024 poz. 1902): yearly net sales to consumers. */
    public const REGISTER_YEARLY_LIMIT = 20000.0;

    private const LIMIT_STATUSES = ['processing', 'completed', 'on-hold'];

    /**
     * Running totals for the two limits, in PLN: gross business sales this
     * month (orders with a VAT ID, which are the invoices KSeF counts;
     * consumer invoices do not count, MF FAQ q. 11) and net consumer sales
     * this year. Orders in another currency use today's NBP table A rate, so
     * those sums are an estimate. Cached for an hour.
     *
     * @return array{ksef_month: float, ksef_exceeded: string, register_year: float, estimated: bool}
     */
    public static function limits(): array
    {
        $cached = get_transient('polski_ksef_limits');
        if (is_array($cached) && isset($cached['ksef_month'], $cached['register_year'])) {
            return [
                'ksef_month' => (float) $cached['ksef_month'],
                'ksef_exceeded' => (string) ($cached['ksef_exceeded'] ?? ''),
                'register_year' => (float) $cached['register_year'],
                'estimated' => ! empty($cached['estimated']),
            ];
        }

        $now = current_datetime();
        $result = ['ksef_month' => 0.0, 'ksef_exceeded' => (string) get_option('polski_ksef_limit_exceeded', ''), 'register_year' => 0.0, 'estimated' => false];

        $month = $now->format('Y-m');
        // Batches of 200, so a shop with a year of history does not load every order at once.
        for ($page = 1; function_exists('wc_get_orders'); $page++) {
            $orders = wc_get_orders([
                'status' => self::LIMIT_STATUSES,
                'date_created' => $now->format('Y') . '-01-01...' . $now->format('Y-m-d'),
                'type' => 'shop_order',
                'limit' => 200,
                'paged' => $page,
            ]);
            if (! is_array($orders) || $orders === []) {
                break;
            }
            foreach ($orders as $order) {
                if (! $order instanceof \WC_Order) {
                    continue;
                }
                $rate = self::plnRate($order->get_currency());
                if ($rate === null) {
                    continue;
                }
                $result['estimated'] = $result['estimated'] || $rate !== 1.0;
                $gross = ((float) $order->get_total() - (float) $order->get_total_refunded()) * $rate;
                $net = $gross - ((float) $order->get_total_tax() - (float) $order->get_total_tax_refunded()) * $rate;
                $created = $order->get_date_created();

                if (self::orderNip($order) !== '') {
                    if ($created !== null && $created->date('Y-m') === $month) {
                        $result['ksef_month'] += $gross;
                    }
                } else {
                    $result['register_year'] += $net;
                }
            }
            if (count($orders) < 200) {
                break;
            }
        }

        // Once over the limit, KSeF stays mandatory from that invoice on, even in a later quieter month (MF FAQ q. 13).
        if ($result['ksef_exceeded'] === '' && $result['ksef_month'] > self::KSEF_MONTHLY_LIMIT) {
            $result['ksef_exceeded'] = $now->format('Y-m-d');
            update_option('polski_ksef_limit_exceeded', $result['ksef_exceeded'], false);
        }

        set_transient('polski_ksef_limits', $result, HOUR_IN_SECONDS);

        return $result;
    }

    /** PLN per unit of $currency from NBP table A (cached a day); null when it cannot be fetched. */
    private static function plnRate(string $currency): ?float
    {
        $currency = strtoupper($currency);
        if ($currency === 'PLN' || $currency === '') {
            return 1.0;
        }

        $key = 'polski_nbp_' . strtolower($currency);
        $cached = get_transient($key);
        if (is_numeric($cached)) {
            return (float) $cached;
        }

        $response = wp_remote_get('https://api.nbp.pl/api/exchangerates/rates/a/' . rawurlencode(strtolower($currency)) . '/?format=json', ['timeout' => 5]);
        $body = is_wp_error($response) ? null : json_decode((string) wp_remote_retrieve_body($response), true);
        $mid = is_array($body) ? ($body['rates'][0]['mid'] ?? null) : null;
        if (! is_numeric($mid)) {
            return null;
        }

        set_transient($key, (float) $mid, DAY_IN_SECONDS);

        return (float) $mid;
    }

    private static function orderNip(\WC_Order $order): string
    {
        foreach (['_billing_nip', '_polski_billing_nip', '_wc_other/polski/nip', '_wc_billing/polski/nip'] as $key) {
            $nip = trim((string) $order->get_meta($key, true));
            if ($nip !== '') {
                return $nip;
            }
        }

        return '';
    }

    /**
     * Render KSeF status in the admin order billing section.
     */
    public function renderKSeFStatus(\WC_Order $order): void
    {
        $required = $order->get_meta('_polski_ksef_required', true);

        if ($required !== 'yes') {
            return;
        }

        $status = $order->get_meta('_polski_ksef_status', true) ?: __('pending', 'polski');

        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html__('KSeF', 'polski'),
            esc_html($status),
        );
    }

    /**
     * Add KSeF column to WooCommerce orders list.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addOrderColumn(array $columns): array
    {
        $columns['polski_ksef'] = __('KSeF', 'polski');

        return $columns;
    }

    /**
     * Render KSeF column for legacy (CPT) orders.
     */
    public function renderOrderColumn(string $column, int $postId): void
    {
        if ($column !== 'polski_ksef') {
            return;
        }

        $order = wc_get_order($postId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        $this->echoKSeFBadge($order);
    }

    /**
     * Render KSeF column for HPOS orders.
     */
    public function renderHPOSOrderColumn(string $column, \WC_Order $order): void
    {
        if ($column !== 'polski_ksef') {
            return;
        }

        $this->echoKSeFBadge($order);
    }

    /**
     * Output KSeF status badge HTML.
     */
    private function echoKSeFBadge(\WC_Order $order): void
    {
        $required = $order->get_meta('_polski_ksef_required', true);

        if ($required !== 'yes') {
            echo '<span style="color:#ccc;">-</span>';
            return;
        }

        $status = $order->get_meta('_polski_ksef_status', true) ?: 'pending';
        // 'accepted' is what PRO mirrors once KSeF numbers the invoice; 'sent'
        // stays green for orders written by older versions.
        $color = in_array($status, ['accepted', 'sent'], true) ? '#46b450' : '#f0ad4e';

        echo '<span style="color:' . esc_attr($color) . ';" title="' . esc_attr($status) . '">&#9679; KSeF</span>';
    }
}
