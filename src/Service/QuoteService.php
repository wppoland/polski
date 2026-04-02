<?php

declare(strict_types=1);

namespace Spolszczony\Service;

use Spolszczony\Contract\Bootable;
use Spolszczony\Contract\HasHooks;
use Spolszczony\Enum\QuoteRequestStatus;
use Spolszczony\Model\QuoteRequest;
use Spolszczony\Repository\ConsentLogRepository;
use Spolszczony\Repository\QuoteRequestRepository;
use Spolszczony\Util\TemplateLoader;

final class QuoteService implements Bootable, HasHooks
{
    private bool $enabled = false;

    public function __construct(
        private readonly QuoteRequestRepository $repository,
        private readonly ConsentLogRepository $consentLog,
        private readonly TemplateLoader $templateLoader,
    ) {
    }

    public function boot(): void
    {
        $settings = get_option('spolszczony_quote', []);
        $this->enabled = is_array($settings) && (bool) ($settings['enabled'] ?? false);
    }

    public function registerHooks(): void
    {
        if (! $this->enabled) {
            return;
        }

        add_action('woocommerce_single_product_summary', [$this, 'renderQuoteButton'], 35);
        add_action('wp_ajax_spolszczony_submit_quote', [$this, 'handleSubmission']);
        add_action('wp_ajax_nopriv_spolszczony_submit_quote', [$this, 'handleSubmission']);
    }

    public function renderQuoteButton(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        $settings = $this->getSettings();
        $buttonText = $settings['button_text'] ?? 'Zapytaj o wycenę';

        printf(
            '<button type="button" class="button spolszczony-quote-btn" data-product-id="%d">%s</button>',
            $product->get_id(),
            esc_html($buttonText),
        );
    }

    public function handleSubmission(): void
    {
        check_ajax_referer('spolszczony_quote', '_nonce');

        $productId = (int) ($_POST['product_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));

        if ($productId <= 0 || $name === '' || $email === '') {
            wp_send_json_error(['message' => 'Wypełnij wszystkie wymagane pola.']);
        }

        $id = $this->repository->create(
            productId: $productId,
            variationId: ((int) ($_POST['variation_id'] ?? 0)) ?: null,
            customerId: get_current_user_id() ?: null,
            customerName: $name,
            customerEmail: $email,
            customerPhone: sanitize_text_field(wp_unslash($_POST['phone'] ?? '')) ?: null,
            companyName: sanitize_text_field(wp_unslash($_POST['company'] ?? '')) ?: null,
            nip: sanitize_text_field(wp_unslash($_POST['nip'] ?? '')) ?: null,
            quantity: (string) max(1, (int) ($_POST['quantity'] ?? 1)),
            postcode: sanitize_text_field(wp_unslash($_POST['postcode'] ?? '')) ?: null,
            message: sanitize_textarea_field(wp_unslash($_POST['message'] ?? '')) ?: null,
            source: 'product_page',
            sourceUrl: wp_get_referer() ?: null,
            consented: ! empty($_POST['privacy_consent']),
        );

        if ($id > 0) {
            do_action('spolszczony/quote/submitted', $id, $productId);
            $successText = $this->getSettings()['success_text'] ?? 'Twoje zapytanie zostało wysłane.';
            wp_send_json_success(['message' => $successText]);
        }

        wp_send_json_error(['message' => 'Nie udało się wysłać zapytania. Spróbuj ponownie.']);
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

    /** @return array<string, mixed> */
    private function getSettings(): array
    {
        $settings = get_option('spolszczony_quote', []);
        return is_array($settings) ? $settings : [];
    }
}
