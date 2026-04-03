<?php

declare(strict_types=1);

namespace Polski\Admin;

use Polski\Contract\HasHooks;
use Polski\Enum\QuoteRequestStatus;
use Polski\Service\QuoteService;

/**
 * Admin list table for quote requests.
 */
final class QuoteRequestsPage implements HasHooks
{
    private const PAGE_SLUG = 'polski-quotes';

    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    public function registerHooks(): void
    {
        // Register after the top-level Polski menu to avoid malformed submenu URLs.
        add_action('admin_menu', [$this, 'registerPage'], 20);
        add_action('admin_post_polski_update_quote_request', [$this, 'handleStatusUpdate']);
    }

    public function registerPage(): void
    {
        $settings = $this->getSettings();

        add_submenu_page(
            'polski',
            (string) ($settings['admin_page_title'] ?? __('Zapytania ofertowe', 'polski')),
            (string) ($settings['admin_page_title'] ?? __('Zapytania ofertowe', 'polski')),
            'manage_woocommerce',
            self::PAGE_SLUG,
            [$this, 'render'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = get_option('polski_quote', []);

        return is_array($settings) ? $settings : [];
    }

    public function handleStatusUpdate(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('Przepraszamy, ale wydaje się, że nie masz dostępu do tej strony.', 'polski'));
        }

        check_admin_referer('polski_update_quote_request');

        $requestId = (int) wp_unslash($_GET['request_id'] ?? 0);
        $statusValue = sanitize_key((string) wp_unslash($_GET['status'] ?? ''));
        $status = QuoteRequestStatus::tryFrom($statusValue);

        if ($requestId <= 0 || ! $status instanceof QuoteRequestStatus) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
            exit;
        }

        $this->quoteService->updateRequestStatus($requestId, $status);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => self::PAGE_SLUG,
                    'quote_updated' => 1,
                ],
                admin_url('admin.php'),
            ),
        );
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('Przepraszamy, ale wydaje się, że nie masz dostępu do tej strony.', 'polski'));
        }

        $settings = $this->getSettings();
        $selectedStatus = QuoteRequestStatus::tryFrom(sanitize_key((string) wp_unslash($_GET['status'] ?? '')));
        $requests = $this->quoteService->getRequests(100, $selectedStatus);
        $counts = $this->quoteService->getStatusCounts();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html((string) ($settings['admin_page_title'] ?? __('Zapytania ofertowe', 'polski'))) . '</h1>';

        if (isset($_GET['quote_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html((string) ($settings['admin_success_notice'] ?? __('Status zapytania został zaktualizowany.', 'polski'))) . '</p></div>';
        }

        echo '<div class="subsubsub" style="margin-bottom:16px;">';
        $allUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
        $allClass = $selectedStatus === null ? 'current' : '';
        echo '<a class="' . esc_attr($allClass) . '" href="' . esc_url($allUrl) . '">' . esc_html((string) ($settings['admin_filter_all_label'] ?? __('Wszystkie', 'polski'))) . '</a>';

        foreach (QuoteRequestStatus::cases() as $status) {
            $url = add_query_arg(
                [
                    'page' => self::PAGE_SLUG,
                    'status' => $status->value,
                ],
                admin_url('admin.php'),
            );
            $class = $selectedStatus === $status ? 'current' : '';
            echo ' | <a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($status->label()) . ' <span class="count">(' . esc_html((string) ($counts[$status->value] ?? 0)) . ')</span></a>';
        }

        echo '</div>';

        echo '<table class="widefat striped" style="margin-top:16px;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html((string) ($settings['admin_column_date'] ?? __('Data', 'polski'))) . '</th>';
        echo '<th>' . esc_html((string) ($settings['admin_column_product'] ?? __('Produkt', 'polski'))) . '</th>';
        echo '<th>' . esc_html((string) ($settings['admin_column_customer'] ?? __('Klient', 'polski'))) . '</th>';
        echo '<th>' . esc_html((string) ($settings['admin_column_company'] ?? __('Firma / NIP', 'polski'))) . '</th>';
        echo '<th>' . esc_html((string) ($settings['admin_column_quantity'] ?? __('Ilość', 'polski'))) . '</th>';
        echo '<th>' . esc_html((string) ($settings['admin_column_status'] ?? __('Status', 'polski'))) . '</th>';
        echo '<th>' . esc_html((string) ($settings['admin_column_actions'] ?? __('Akcje', 'polski'))) . '</th>';
        echo '</tr></thead><tbody>';

        if ($requests === []) {
            echo '<tr><td colspan="7">' . esc_html((string) ($settings['admin_empty_text'] ?? __('Brak zapytań ofertowych.', 'polski'))) . '</td></tr>';
        }

        foreach ($requests as $request) {
            $product = wc_get_product($request->variationId ?? $request->productId);
            $productLabel = $product instanceof \WC_Product ? $product->get_name() : '#' . $request->productId;

            echo '<tr>';
            echo '<td>' . esc_html($request->createdAt->format((string) ($settings['admin_date_format'] ?? 'Y-m-d H:i'))) . '</td>';
            echo '<td><strong>' . esc_html($productLabel) . '</strong><br><small>' . esc_html($request->message ?? '-') . '</small></td>';
            echo '<td>';
            echo esc_html($request->customerName) . '<br>';
            echo '<a href="mailto:' . esc_attr($request->customerEmail) . '">' . esc_html($request->customerEmail) . '</a>';
            if ($request->customerPhone !== null) {
                echo '<br><small>' . esc_html($request->customerPhone) . '</small>';
            }
            if ($request->postcode !== null) {
                echo '<br><small>' . esc_html((string) ($settings['admin_postcode_label'] ?? __('Kod', 'polski'))) . ': ' . esc_html($request->postcode) . '</small>';
            }
            echo '</td>';
            echo '<td>' . esc_html($request->companyName ?? '-') . '<br><small>' . esc_html($request->nip ?? '-') . '</small></td>';
            echo '<td>' . esc_html($request->quantity) . '</td>';
            echo '<td>' . $this->renderStatusBadge($request->status) . '</td>';
            echo '<td>' . $this->renderActions($request->id, $request->status) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function renderStatusBadge(QuoteRequestStatus $status): string
    {
        $color = match ($status) {
            QuoteRequestStatus::New => '#2271b1',
            QuoteRequestStatus::Contacted => '#dba617',
            QuoteRequestStatus::Quoted => '#7a54a8',
            QuoteRequestStatus::Won => '#46b450',
            QuoteRequestStatus::Lost => '#d63638',
            QuoteRequestStatus::Archived => '#646970',
        };

        return sprintf(
            '<span style="display:inline-block;padding:3px 8px;border-radius:999px;background:%1$s;color:#fff;font-size:12px;">%2$s</span>',
            esc_attr($color),
            esc_html($status->label()),
        );
    }

    private function renderActions(int $requestId, QuoteRequestStatus $currentStatus): string
    {
        $actions = [];

        foreach (QuoteRequestStatus::cases() as $status) {
            if ($status === $currentStatus) {
                continue;
            }

            $actions[] = sprintf(
                '<a class="button button-small" href="%s">%s</a>',
                esc_url($this->getActionUrl($requestId, $status)),
                esc_html($status->label()),
            );
        }

        return implode(' ', $actions);
    }

    private function getActionUrl(int $requestId, QuoteRequestStatus $status): string
    {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action' => 'polski_update_quote_request',
                    'request_id' => $requestId,
                    'status' => $status->value,
                ],
                admin_url('admin-post.php'),
            ),
            'polski_update_quote_request',
        );
    }
}
