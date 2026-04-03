<?php

declare(strict_types=1);

namespace Polski\Service;

use Polski\Contract\Bootable;
use Polski\Contract\HasHooks;
use Polski\Enum\CheckboxContext;
use Polski\Enum\QuoteRequestStatus;
use Polski\Model\QuoteRequest;
use Polski\Repository\ConsentLogRepository;
use Polski\Repository\QuoteRequestRepository;
use Polski\Util\Sanitizer;
use Polski\Util\SettingsCacheable;
use Polski\Util\TemplateLoader;

final class QuoteService implements Bootable, HasHooks
{
    use SettingsCacheable;

    private const OPTION = 'polski_quote';

    public function __construct(
        private readonly QuoteRequestRepository $repository,
        private readonly ConsentLogRepository $consentLog,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
    }

    public function registerHooks(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('woocommerce_single_product_summary', [$this, 'renderQuoteButton'], 35);
        add_filter('woocommerce_loop_add_to_cart_link', [$this, 'filterLoopAddToCartLink'], 35, 3);
        add_filter('woocommerce_is_purchasable', [$this, 'filterPurchasable'], 35, 2);
        add_filter('woocommerce_get_price_html', [$this, 'filterPriceHtml'], 35, 2);
        add_action('admin_post_polski_submit_quote_request', [$this, 'handleFormSubmission']);
        add_action('admin_post_nopriv_polski_submit_quote_request', [$this, 'handleFormSubmission']);
        add_action('wp_ajax_polski_submit_quote', [$this, 'handleAjaxSubmission']);
        add_action('wp_ajax_nopriv_polski_submit_quote', [$this, 'handleAjaxSubmission']);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->getSettings()['enabled'] ?? false);
    }

    public function enqueueAssets(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! is_product() && ! (bool) ($this->getSettings()['show_on_loop'] ?? false)) {
            return;
        }

        wp_enqueue_style(
            'polski-request-quote',
            \Polski\Plugin::instance()->url('assets/css/request-quote.css'),
            [],
            \Polski\VERSION,
        );

        wp_enqueue_script(
            'polski-request-quote',
            \Polski\Plugin::instance()->url('assets/js/request-quote.js'),
            [],
            \Polski\VERSION,
            true,
        );
    }

    public function renderQuoteButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        if (! $this->isAvailableForProduct($product) || ! (bool) ($this->getSettings()['show_on_single'] ?? true)) {
            return;
        }

        $this->templateLoader->include('single-product/request-quote', [
            'service' => $this,
            'settings' => $this->getSettings(),
            'privacy_label' => wp_kses_post((string) ($this->getSettings()['privacy_label'] ?? '')),
            'product' => $product,
            'success' => isset($_GET['polski_quote']),
            'error' => sanitize_text_field((string) wp_unslash($_GET['polski_quote_error'] ?? '')),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function filterLoopAddToCartLink(string $html, \WC_Product $product, array $args): string
    {
        if (! $this->isAvailableForProduct($product) || ! (bool) ($this->getSettings()['show_on_loop'] ?? false)) {
            return $html;
        }

        return $this->templateLoader->render('loop/request-quote', [
            'service' => $this,
            'product' => $product,
            'button_url' => $this->getLoopButtonUrl($product),
        ]);
    }

    public function filterPurchasable(bool $purchasable, \WC_Product $product): bool
    {
        if (! $this->isQuoteOnlyProduct($product) || ! (bool) ($this->getSettings()['replace_add_to_cart'] ?? false)) {
            return $purchasable;
        }

        return false;
    }

    public function filterPriceHtml(string $priceHtml, \WC_Product $product): string
    {
        if (! $this->isQuoteOnlyProduct($product) || ! (bool) ($this->getSettings()['hide_prices'] ?? false)) {
            return $priceHtml;
        }

        return sprintf(
            '<span class="polski-quote-price-placeholder">%s</span>',
            esc_html((string) ($this->getSettings()['price_placeholder_text'] ?? __('Cena dostępna po wycenie', 'polski'))),
        );
    }

    public function isAvailableForProduct(\WC_Product $product): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! $this->allowsCurrentVisitor()) {
            return false;
        }

        if ($this->getProductSetting($product, '_polski_quote_enabled') === 'yes') {
            return true;
        }

        return ($this->getSettings()['availability'] ?? 'selected') === 'all_products';
    }

    public function getButtonText(\WC_Product $product): string
    {
        $custom = trim($this->getProductSetting($product, '_polski_quote_button_text'));

        if ($custom !== '') {
            return $custom;
        }

        return (string) ($this->getSettings()['button_text'] ?? __('Zapytaj o wycenę', 'polski'));
    }

    public function getMinimumQuantity(\WC_Product $product): string
    {
        $minimum = (float) $this->getProductSetting($product, '_polski_quote_min_qty');

        if ($minimum <= 0) {
            $minimum = 1.0;
        }

        return wc_format_decimal((string) $minimum, 3);
    }

    public function getLoopButtonUrl(\WC_Product $product): string
    {
        $url = get_permalink($product->get_id()) ?: home_url('/');

        return add_query_arg('polski_quote', '1', $url);
    }

    public function handleFormSubmission(): void
    {
        $result = $this->storeRequestFromCurrentPayload(false);
        $redirectUrl = $this->getRedirectUrl();

        if (! $result['success']) {
            wp_safe_redirect(add_query_arg([
                'polski_quote' => '1',
                'polski_quote_error' => $result['message'],
            ], $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'polski_quote' => '1',
            'polski_quote_message' => $result['message'],
        ], $redirectUrl));
        exit;
    }

    public function handleAjaxSubmission(): void
    {
        $result = $this->storeRequestFromCurrentPayload(true);

        if (! $result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success(['message' => $result['message']]);
    }

    public function updateRequestStatus(int $requestId, QuoteRequestStatus $status): bool
    {
        return $this->repository->updateStatus($requestId, $status);
    }

    /**
     * @return list<QuoteRequest>
     */
    public function getRequests(int $limit = 100, ?QuoteRequestStatus $status = null): array
    {
        return $this->repository->findAll($limit, 0, $status);
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $counts = [];

        foreach (QuoteRequestStatus::cases() as $status) {
            $counts[$status->value] = $this->repository->countByStatus($status);
        }

        return $counts;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function storeRequestFromCurrentPayload(bool $isAjax): array
    {
        if ($isAjax) {
            check_ajax_referer('polski_quote', '_nonce');
        } else {
            check_admin_referer('polski_quote_request', '_polski_quote_nonce');
        }

        $productId = (int) ($_POST['polski_quote_product_id'] ?? $_POST['product_id'] ?? 0);
        $variationId = (int) ($_POST['polski_quote_variation_id'] ?? $_POST['variation_id'] ?? 0);
        $product = wc_get_product($variationId > 0 ? $variationId : $productId);

        if (! $product instanceof \WC_Product || ! $this->isAvailableForProduct($product)) {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['product_unavailable_text'] ?? __('Nie udało się przygotować zapytania dla tego produktu.', 'polski')),
            ];
        }

        $name = sanitize_text_field((string) wp_unslash($_POST['polski_quote_name'] ?? $_POST['name'] ?? ''));
        $email = sanitize_email((string) wp_unslash($_POST['polski_quote_email'] ?? $_POST['email'] ?? ''));
        $phone = sanitize_text_field((string) wp_unslash($_POST['polski_quote_phone'] ?? $_POST['phone'] ?? ''));
        $company = sanitize_text_field((string) wp_unslash($_POST['polski_quote_company'] ?? $_POST['company'] ?? ''));
        $nip = Sanitizer::nip((string) wp_unslash($_POST['polski_quote_nip'] ?? $_POST['nip'] ?? ''));
        $postcode = sanitize_text_field((string) wp_unslash($_POST['polski_quote_postcode'] ?? $_POST['postcode'] ?? ''));
        $message = sanitize_textarea_field((string) wp_unslash($_POST['polski_quote_message'] ?? $_POST['message'] ?? ''));
        $quantity = wc_format_decimal((string) ($_POST['polski_quote_quantity'] ?? $_POST['quantity'] ?? '1'), 3);
        $consented = ! empty($_POST['polski_quote_privacy']) || ! empty($_POST['privacy_consent']);

        if ($name === '' || $email === '' || ! is_email($email)) {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['invalid_contact_text'] ?? __('Uzupełnij poprawnie imię i adres email.', 'polski')),
            ];
        }

        if (! empty($this->getSettings()['require_phone']) && $phone === '') {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['phone_required_text'] ?? __('Podaj numer telefonu do kontaktu.', 'polski')),
            ];
        }

        if (! empty($this->getSettings()['require_company']) && $company === '') {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['company_required_text'] ?? __('Podaj nazwę firmy.', 'polski')),
            ];
        }

        if (! empty($this->getSettings()['require_nip'])) {
            if ($nip === '') {
                return [
                    'success' => false,
                    'message' => (string) ($this->getSettings()['nip_required_text'] ?? __('Podaj numer NIP.', 'polski')),
                ];
            }

            if (! $this->isValidNip($nip)) {
                return [
                    'success' => false,
                    'message' => (string) ($this->getSettings()['nip_invalid_text'] ?? __('Podaj poprawny numer NIP.', 'polski')),
                ];
            }
        }

        if (! empty($this->getSettings()['require_postcode']) && $postcode === '') {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['postcode_required_text'] ?? __('Podaj kod pocztowy.', 'polski')),
            ];
        }

        if (! empty($this->getSettings()['privacy_required']) && ! $consented) {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['privacy_required_text'] ?? __('Musisz zaakceptować zgodę prywatności.', 'polski')),
            ];
        }

        $minimumQuantity = (float) $this->getMinimumQuantity($product);

        if ((float) $quantity < $minimumQuantity) {
            return [
                'success' => false,
                'message' => str_replace(
                    '{quantity}',
                    wc_clean((string) $minimumQuantity),
                    (string) ($this->getSettings()['minimum_quantity_text'] ?? __('Minimalna ilość dla tego zapytania to {quantity}.', 'polski'))
                ),
            ];
        }

        $requestId = $this->repository->create(
            productId: $product->get_id(),
            variationId: $variationId > 0 ? $variationId : null,
            customerId: get_current_user_id() ?: null,
            customerName: $name,
            customerEmail: $email,
            customerPhone: $phone !== '' ? $phone : null,
            companyName: $company !== '' ? $company : null,
            nip: $nip !== '' ? $nip : null,
            quantity: $quantity,
            postcode: $postcode !== '' ? $postcode : null,
            message: $message !== '' ? $message : null,
            source: $isAjax ? 'ajax' : 'product_page',
            sourceUrl: esc_url_raw((string) wp_unslash($_POST['polski_quote_source_url'] ?? wp_get_referer() ?? '')) ?: null,
            consented: $consented,
            meta: [
                'product_name' => $product->get_name(),
                'quote_only' => $this->isQuoteOnlyProduct($product),
            ],
        );

        if ($requestId <= 0) {
            return [
                'success' => false,
                'message' => (string) ($this->getSettings()['save_error_text'] ?? __('Nie udało się zapisać zapytania. Spróbuj ponownie.', 'polski')),
            ];
        }

        if ($consented) {
            $this->consentLog->log(
                'quote_privacy',
                CheckboxContext::Quote,
                true,
                get_current_user_id() ?: null,
                wp_get_session_token() ?: null,
            );
        }

        do_action('polski/quote/submitted', $requestId, $product->get_id());

        return [
            'success' => true,
            'message' => (string) ($this->getSettings()['success_text'] ?? __('Twoje zapytanie zostało wysłane.', 'polski')),
        ];
    }

    private function allowsCurrentVisitor(): bool
    {
        if (is_user_logged_in()) {
            return true;
        }

        return (bool) ($this->getSettings()['allow_guest'] ?? true);
    }

    private function isQuoteOnlyProduct(\WC_Product $product): bool
    {
        if (! $this->isAvailableForProduct($product)) {
            return false;
        }

        return $this->getProductSetting($product, '_polski_quote_only') === 'yes';
    }

    private function getProductSetting(\WC_Product $product, string $metaKey): string
    {
        $value = (string) $product->get_meta($metaKey, true);

        if ($value !== '') {
            return $value;
        }

        if ($product->is_type('variation')) {
            $parentId = $product->get_parent_id();

            if ($parentId > 0) {
                return (string) get_post_meta($parentId, $metaKey, true);
            }
        }

        return '';
    }

    private function getRedirectUrl(): string
    {
        $url = esc_url_raw((string) wp_unslash($_POST['polski_quote_source_url'] ?? ''));

        if ($url !== '') {
            return $url;
        }

        return wp_get_referer() ?: home_url('/');
    }

    private function isValidNip(string $nip): bool
    {
        if (! preg_match('/^\d{10}$/', $nip)) {
            return false;
        }

        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $nip[$index]) * $weight;
        }

        return $sum % 11 === (int) $nip[9];
    }
}
