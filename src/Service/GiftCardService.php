<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Admin\ModulesPage;
use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Model\GiftCard;
use Polski\Repository\GiftCardRepository;
use Polski\Util\Formatter;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

/**
 * Gift card purchase and redemption flow.
 */
final class GiftCardService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_gift_cards';
    private const ENDPOINT = 'polski-gift-cards';
    private const SESSION_CODES = 'polski_gift_card_codes';
    private const SESSION_USAGE = 'polski_gift_card_usage';

    public function __construct(
        private readonly GiftCardRepository $repository,
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
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderProductFields'], 7);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddToCart'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addCartItemData'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyLinePrice'], 22);
        add_filter('woocommerce_get_item_data', [$this, 'renderItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addOrderItemMeta'], 10, 4);
        add_action('woocommerce_checkout_create_order', [$this, 'storeOrderMeta'], 20, 2);
        add_action('wp_loaded', [$this, 'handleGiftCardActions']);
        add_action('woocommerce_before_cart_totals', [$this, 'renderRedeemForm']);
        add_action('woocommerce_review_order_before_payment', [$this, 'renderRedeemForm']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'applyGiftCardFees']);
        add_action('woocommerce_order_status_processing', [$this, 'createGiftCardsFromOrder']);
        add_action('woocommerce_order_status_completed', [$this, 'createGiftCardsFromOrder']);
        add_action('woocommerce_payment_complete', [$this, 'redeemAppliedGiftCards']);
        add_action('woocommerce_order_status_processing', [$this, 'redeemAppliedGiftCards']);
        add_action('woocommerce_order_status_completed', [$this, 'redeemAppliedGiftCards']);
        add_filter('woocommerce_account_menu_items', [$this, 'addAccountMenuItem']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'renderAccountPage']);
    }

    public function isEnabled(): bool
    {
        return ModulesPage::isModuleEnabled('gift_cards');
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
            'polski-gift-cards',
            \Polski\Plugin::instance()->url('assets/css/gift-cards.css'),
            [],
            \Polski\VERSION,
        );
    }

    public function renderProductFields(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->isGiftCardProduct($product) || ! ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $this->templateLoader->include('single-product/gift-card-fields', [
            'service' => $this,
            'product' => $product,
            'amounts' => $this->getAmounts($product),
        ]);
    }

    public function validateAddToCart(bool $passed, int $productId, int $quantity): bool
    {
        $product = wc_get_product($productId);

        if (! $product instanceof \WC_Product || ! $this->isGiftCardProduct($product)) {
            return $passed;
        }

        $recipientName = sanitize_text_field((string) wp_unslash($_POST['polski_gift_card_recipient_name'] ?? ''));
        $recipientEmail = sanitize_email((string) wp_unslash($_POST['polski_gift_card_recipient_email'] ?? ''));
        $senderName = sanitize_text_field((string) wp_unslash($_POST['polski_gift_card_sender_name'] ?? ''));
        $amount = $this->resolveAmountFromRequest($product);

        if ($recipientName === '' || $senderName === '') {
            wc_add_notice((string) ($this->getSettings()['recipient_required_text'] ?? __('Uzupełnij dane nadawcy i odbiorcy karty podarunkowej.', 'polski')), 'error');
            return false;
        }

        if (! is_email($recipientEmail)) {
            wc_add_notice((string) ($this->getSettings()['recipient_email_error_text'] ?? __('Podaj poprawny adres email odbiorcy.', 'polski')), 'error');
            return false;
        }

        if ($amount <= 0) {
            wc_add_notice((string) ($this->getSettings()['amount_error_text'] ?? __('Wybierz poprawną kwotę karty podarunkowej.', 'polski')), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * @param array<string, mixed> $cartItemData
     * @return array<string, mixed>
     */
    public function addCartItemData(array $cartItemData, int $productId, int $variationId): array
    {
        $product = wc_get_product($variationId > 0 ? $variationId : $productId);

        if (! $product instanceof \WC_Product || ! $this->isGiftCardProduct($product)) {
            return $cartItemData;
        }

        $purchase = [
            'recipient_name' => sanitize_text_field((string) wp_unslash($_POST['polski_gift_card_recipient_name'] ?? '')),
            'recipient_email' => sanitize_email((string) wp_unslash($_POST['polski_gift_card_recipient_email'] ?? '')),
            'sender_name' => sanitize_text_field((string) wp_unslash($_POST['polski_gift_card_sender_name'] ?? '')),
            'message' => sanitize_textarea_field((string) wp_unslash($_POST['polski_gift_card_message'] ?? '')),
            'amount' => $this->resolveAmountFromRequest($product),
            'currency' => get_woocommerce_currency(),
        ];

        $cartItemData['polski_gift_card_purchase'] = $purchase;
        $cartItemData['polski_gift_card_price'] = (float) $purchase['amount'];
        $cartItemData['polski_gift_card_hash'] = md5(wp_json_encode($purchase) ?: '');

        return $cartItemData;
    }

    public function applyLinePrice(\WC_Cart $cart): void
    {
        if (is_admin() && ! defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $item) {
            if (empty($item['polski_gift_card_purchase']) || ! isset($item['data']) || ! $item['data'] instanceof \WC_Product) {
                continue;
            }

            $item['data']->set_price((float) ($item['polski_gift_card_price'] ?? 0));
        }
    }

    /**
     * @param list<array{name: string, value: string}> $itemData
     * @param array<string, mixed>                     $cartItem
     * @return list<array{name: string, value: string}>
     */
    public function renderItemData(array $itemData, array $cartItem): array
    {
        if (empty($cartItem['polski_gift_card_purchase']) || ! is_array($cartItem['polski_gift_card_purchase'])) {
            return $itemData;
        }

        $purchase = $cartItem['polski_gift_card_purchase'];
        $itemData[] = ['name' => (string) ($this->getSettings()['recipient_name_label'] ?? __('Odbiorca', 'polski')), 'value' => (string) ($purchase['recipient_name'] ?? '')];
        $itemData[] = ['name' => (string) ($this->getSettings()['recipient_email_label'] ?? __('Email odbiorcy', 'polski')), 'value' => (string) ($purchase['recipient_email'] ?? '')];
        $itemData[] = ['name' => (string) ($this->getSettings()['sender_name_label'] ?? __('Nadawca', 'polski')), 'value' => (string) ($purchase['sender_name'] ?? '')];

        if (! empty($purchase['message'])) {
            $itemData[] = ['name' => (string) ($this->getSettings()['message_label'] ?? __('Wiadomość', 'polski')), 'value' => (string) $purchase['message']];
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function addOrderItemMeta(\WC_Order_Item_Product $item, string $cartItemKey, array $values, \WC_Order $order): void
    {
        if (empty($values['polski_gift_card_purchase']) || ! is_array($values['polski_gift_card_purchase'])) {
            return;
        }

        $purchase = $values['polski_gift_card_purchase'];
        $item->add_meta_data('_polski_gift_card_purchase', wp_json_encode($purchase), true);
        $item->add_meta_data((string) ($this->getSettings()['recipient_name_label'] ?? __('Odbiorca', 'polski')), (string) ($purchase['recipient_name'] ?? ''), true);
        $item->add_meta_data((string) ($this->getSettings()['recipient_email_label'] ?? __('Email odbiorcy', 'polski')), (string) ($purchase['recipient_email'] ?? ''), true);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeOrderMeta(\WC_Order $order, array $data): void
    {
        $usage = $this->getGiftCardUsage();

        if ($usage === []) {
            return;
        }

        $order->update_meta_data('_polski_gift_card_usage', wp_json_encode($usage));
    }

    public function handleGiftCardActions(): void
    {
        if (! $this->isEnabled() || ! isset($_POST['polski_gift_card_action'])) {
            return;
        }

        check_admin_referer('polski_gift_card_redeem', 'polski_gift_card_nonce');

        $action = sanitize_key((string) wp_unslash($_POST['polski_gift_card_action']));

        if ($action === 'remove') {
            $code = strtoupper(sanitize_text_field((string) wp_unslash($_POST['polski_gift_card_code'] ?? '')));
            $codes = array_values(array_filter($this->getAppliedCodes(), static fn (string $item): bool => $item !== $code));
            $this->setAppliedCodes($codes);
            WC()->session?->set(self::SESSION_USAGE, []);
            wc_add_notice((string) ($this->getSettings()['removed_code_text'] ?? __('Usunięto kartę podarunkową z koszyka.', 'polski')), 'success');
            return;
        }

        $code = strtoupper(sanitize_text_field((string) wp_unslash($_POST['polski_gift_card_code'] ?? '')));
        $card = $this->repository->findByCode($code);

        if ($card === null || ! $this->isRedeemable($card)) {
            wc_add_notice((string) ($this->getSettings()['invalid_code_text'] ?? __('Podany kod karty podarunkowej jest nieprawidłowy lub nieaktywny.', 'polski')), 'error');
            return;
        }

        $codes = $this->getAppliedCodes();

        if (! in_array($code, $codes, true)) {
            $codes[] = $code;
        }

        $this->setAppliedCodes($codes);
        wc_add_notice((string) ($this->getSettings()['applied_code_text'] ?? __('Kod karty podarunkowej został zastosowany.', 'polski')), 'success');
    }

    public function renderRedeemForm(): void
    {
        if (! $this->isEnabled() || (! is_cart() && ! is_checkout())) {
            return;
        }

        $this->templateLoader->include('shared/gift-card-redeem', [
            'service' => $this,
            'codes' => $this->getAppliedCodes(),
        ]);
    }

    public function applyGiftCardFees(\WC_Cart $cart): void
    {
        if (! $this->isEnabled() || $cart->is_empty()) {
            return;
        }

        $available = max(0.0, (float) $cart->get_cart_contents_total() + (float) $cart->get_fee_total());
        $usage = [];

        foreach ($this->getAppliedCodes() as $code) {
            if ($available <= 0) {
                break;
            }

            $card = $this->repository->findByCode($code);

            if ($card === null || ! $this->isRedeemable($card)) {
                continue;
            }

            $amount = min($available, $card->balance);

            if ($amount <= 0) {
                continue;
            }

            $cart->add_fee($this->getFeeLabel($card->code), -1 * $amount);
            $usage[$card->code] = [
                'gift_card_id' => $card->id,
                'amount' => $amount,
            ];
            $available -= $amount;
        }

        WC()->session?->set(self::SESSION_USAGE, $usage);
    }

    public function createGiftCardsFromOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();

            if (! $product instanceof \WC_Product || ! $this->isGiftCardProduct($product)) {
                continue;
            }

            $purchaseJson = (string) $item->get_meta('_polski_gift_card_purchase', true);
            $purchase = json_decode($purchaseJson, true);

            if (! is_array($purchase)) {
                continue;
            }

            $quantity = max(1, (int) $item->get_quantity());

            for ($index = 0; $index < $quantity; $index++) {
                $code = $this->generateCode();

                if ($this->repository->existsForOrderAndCode($orderId, $code)) {
                    continue;
                }

                $expiresAt = null;
                $expiryDays = max(1, (int) ($this->getSettings()['expiry_days'] ?? 365));

                try {
                    $expiresAt = (new \DateTimeImmutable('now', wp_timezone()))->modify('+' . $expiryDays . ' days');
                } catch (\Exception) {
                    $expiresAt = null;
                }

                $this->repository->create([
                    'code' => $code,
                    'initial_balance' => (float) ($purchase['amount'] ?? 0),
                    'balance' => (float) ($purchase['amount'] ?? 0),
                    'currency' => (string) ($purchase['currency'] ?? get_woocommerce_currency()),
                    'purchaser_user_id' => $order->get_user_id(),
                    'purchaser_email' => (string) $order->get_billing_email(),
                    'recipient_name' => (string) ($purchase['recipient_name'] ?? ''),
                    'recipient_email' => (string) ($purchase['recipient_email'] ?? ''),
                    'sender_name' => (string) ($purchase['sender_name'] ?? ''),
                    'message' => (string) ($purchase['message'] ?? ''),
                    'order_id' => $orderId,
                    'product_id' => $product->get_id(),
                    'status' => 'active',
                    'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
                ]);

                $this->sendGiftCardEmail($code, $purchase, $expiresAt);
            }
        }
    }

    public function redeemAppliedGiftCards(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order || $order->get_meta('_polski_gift_cards_redeemed', true) === 'yes') {
            return;
        }

        $usage = json_decode((string) $order->get_meta('_polski_gift_card_usage', true), true);

        if (! is_array($usage) || $usage === []) {
            return;
        }

        foreach ($usage as $code => $item) {
            $card = $this->repository->findByCode((string) $code);

            if ($card === null) {
                continue;
            }

            $amount = (float) ($item['amount'] ?? 0);

            if ($amount > 0) {
                $this->repository->debit($card->id, $orderId, $amount, 'Redeemed on checkout');
            }
        }

        $order->update_meta_data('_polski_gift_cards_redeemed', 'yes');
        $order->save();
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

        $items[self::ENDPOINT] = (string) ($this->getSettings()['account_label'] ?? __('Karty podarunkowe', 'polski'));

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public function renderAccountPage(): void
    {
        $customer = wp_get_current_user();
        $cards = $customer instanceof \WP_User ? $this->repository->findForAccount((int) $customer->ID, (string) $customer->user_email) : [];

        $this->templateLoader->include('account/gift-cards', [
            'service' => $this,
            'cards' => $cards,
        ]);
    }

    public function getDateFormat(): string
    {
        return (string) ($this->getSettings()['date_format'] ?? 'd.m.Y');
    }

    public function renderBalanceForm(): string
    {
        return $this->templateLoader->render('shared/gift-card-redeem', [
            'service' => $this,
            'codes' => $this->getAppliedCodes(),
        ]);
    }

    public function isGiftCardProduct(\WC_Product $product): bool
    {
        return $this->isEnabled() && $product->get_meta('_polski_gift_card_enabled', true) === 'yes';
    }

    /**
     * @return list<float>
     */
    public function getAmounts(\WC_Product $product): array
    {
        $raw = trim((string) $product->get_meta('_polski_gift_card_amounts', true));
        $raw = $raw !== '' ? $raw : (string) ($this->getSettings()['default_amounts'] ?? '50,100,200');
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_filter(array_map(static fn (string $value): float => max(0.0, (float) $value), $parts)));
    }

    /**
     * @return list<string>
     */
    public function getAppliedCodes(): array
    {
        $codes = WC()->session?->get(self::SESSION_CODES, []);

        return is_array($codes) ? array_values(array_filter(array_map('strval', $codes))) : [];
    }

    /**
     * @return array<string, array{gift_card_id:int,amount:float}>
     */
    public function getGiftCardUsage(): array
    {
        $usage = WC()->session?->get(self::SESSION_USAGE, []);

        return is_array($usage) ? $usage : [];
    }

    /**
     * @param list<string> $codes
     */
    private function setAppliedCodes(array $codes): void
    {
        WC()->session?->set(self::SESSION_CODES, $codes);
    }

    private function resolveAmountFromRequest(\WC_Product $product): float
    {
        $selected = (float) wp_unslash($_POST['polski_gift_card_amount'] ?? 0);
        $custom = (float) wp_unslash($_POST['polski_gift_card_custom_amount'] ?? 0);
        $allowCustom = $product->get_meta('_polski_gift_card_allow_custom_amount', true) === 'yes'
            || (bool) ($this->getSettings()['allow_custom_amount'] ?? true);

        if ($allowCustom && $custom > 0) {
            $minMeta = $product->get_meta('_polski_gift_card_min_amount', true);
            $maxMeta = $product->get_meta('_polski_gift_card_max_amount', true);
            $min = max(0.0, (float) ($minMeta !== '' ? $minMeta : ($this->getSettings()['min_amount'] ?? 20)));
            $max = max($min, (float) ($maxMeta !== '' ? $maxMeta : ($this->getSettings()['max_amount'] ?? 1000)));

            return min($max, max($min, $custom));
        }

        return $selected;
    }

    private function isRedeemable(GiftCard $card): bool
    {
        if ($card->status !== 'active' || $card->balance <= 0) {
            return false;
        }

        return $card->expiresAt === null || $card->expiresAt >= new \DateTimeImmutable('now', wp_timezone());
    }

    private function generateCode(): string
    {
        $prefix = strtoupper((string) ($this->getSettings()['code_prefix'] ?? 'SP'));
        return $prefix . '-' . strtoupper(wp_generate_password(10, false, false));
    }

    public function getStatusLabel(GiftCard $card): string
    {
        return match ($card->status) {
            'used' => (string) ($this->getSettings()['status_used'] ?? __('Wykorzystana', 'polski')),
            'expired' => (string) ($this->getSettings()['status_expired'] ?? __('Wygasła', 'polski')),
            default => (string) ($this->getSettings()['status_active'] ?? __('Aktywna', 'polski')),
        };
    }

    private function getFeeLabel(string $code): string
    {
        $template = (string) ($this->getSettings()['fee_label'] ?? __('Karta podarunkowa {code}', 'polski'));

        return str_replace('{code}', $code, $template);
    }

    /**
     * @param array<string, mixed>    $purchase
     * @param \DateTimeImmutable|null $expiresAt
     */
    private function sendGiftCardEmail(string $code, array $purchase, ?\DateTimeImmutable $expiresAt): void
    {
        $subject = Formatter::interpolate(
            (string) ($this->getSettings()['email_subject'] ?? 'Otrzymujesz kartę podarunkową - {code}'),
            ['code' => $code],
        );

        $lines = [
            (string) ($this->getSettings()['email_heading'] ?? __('Ktoś wysłał Ci kartę podarunkową do sklepu', 'polski')),
            '',
            sprintf('%s: %s', __('Kod karty', 'polski'), $code),
            sprintf('%s: %s', __('Wartość', 'polski'), Formatter::price((float) ($purchase['amount'] ?? 0))),
        ];

        $senderName = trim((string) ($purchase['sender_name'] ?? ''));

        if ($senderName !== '') {
            $lines[] = sprintf('%s: %s', __('Od', 'polski'), $senderName);
        }

        $messageText = trim((string) ($purchase['message'] ?? ''));

        if ($messageText !== '') {
            $lines[] = sprintf('%s: %s', __('Wiadomość', 'polski'), $messageText);
        }

        $message = implode("\n", $lines);

        if ($expiresAt !== null) {
            $message .= "\n\n" . sprintf(
                __('Kod ważny do %s.', 'polski'),
                wp_date('j.m.Y', $expiresAt->getTimestamp()),
            );
        }

        wp_mail((string) ($purchase['recipient_email'] ?? ''), $subject, $message);
    }
}
