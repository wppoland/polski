<?php

declare(strict_types=1);

namespace Polski\KSeF\Admin;

use Polski\Contract\HasHooks;
use Polski\KSeF\KSeFSyncService;
use Polski\KSeF\V2\KSeFCredentials;
use Polski\KSeF\V2\VerificationLink;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceType;
use Polski\KSeF\Repository\InvoiceRepository;

/**
 * "KSeF" metabox on the order edit screen (HPOS and legacy), the admin-post
 * handlers behind its buttons, and auto_send.
 *
 * Every invoice of the order that KSeF takes (VAT invoices and corrections)
 * gets its status, KSeF number, environment and last error, with Send,
 * Refresh status and Download UPO. A refund with no correction invoice gets
 * a button to issue one through InvoiceService::createCorrection(). The
 * sending itself stays in KSeFSyncService; handlers only check capability
 * and nonce, call it, and redirect back with a notice.
 *
 * Adapted from FakturFlow KSeF (GPLv2+)
 */
final class KSeFOrderBox implements HasHooks
{
    /** @var list<string> admin-post actions, without the polski_pro_ksef_ prefix. */
    public const ACTIONS = ['send', 'refresh', 'upo', 'correction', 'technical', 'xml'];

    public function __construct(
        private readonly KSeFSyncService $sync,
        private readonly InvoiceRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('admin_notices', [$this, 'renderNotice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('polski/pro/invoice/created', [$this, 'autoSend']);
        // Invoices issued by the free invoices module.
        add_action('polski/ksef/invoice_ready_to_send', fn (int $id) => $this->autoSend($this->repository->findById($id)));

        foreach (self::ACTIONS as $action) {
            add_action('admin_post_polski_pro_ksef_' . $action, [$this, 'handle' . ucfirst($action)]);
        }

        // Bulk "Send invoices to KSeF" on the orders list (HPOS and the post table), e.g. a backlog after an outage.
        foreach (['woocommerce_page_wc-orders', 'edit-shop_order'] as $screen) {
            add_filter('bulk_actions-' . $screen, [$this, 'addBulkAction']);
            add_filter('handle_bulk_actions-' . $screen, [$this, 'handleBulkAction'], 10, 3);
        }
        add_action('admin_notices', [$this, 'renderBulkNotice']);
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addBulkAction(array $actions): array
    {
        $settings = get_option('polski_pro_ksef', []);
        if (is_array($settings) && ! empty($settings['enabled'])) {
            $actions['polski_ksef_send'] = __('Send invoices to KSeF', 'polski');
        }

        return $actions;
    }

    /**
     * Queues every invoice of the chosen orders that KSeF has not taken yet.
     *
     * @param array<int|string> $ids
     */
    public function handleBulkAction(string $redirect, string $action, array $ids): string
    {
        if ($action !== 'polski_ksef_send' || ! current_user_can('manage_woocommerce')) {
            return $redirect;
        }

        $queued = 0;
        foreach (array_map('intval', $ids) as $orderId) {
            foreach ($this->repository->findByOrder($orderId) as $invoice) {
                if ($invoice->type->isAccountingDocument() && ! in_array((string) $invoice->ksefStatus, ['accepted', 'sent', 'submitted', 'queued'], true)
                    && ($this->sync->queueForSending($invoice->id)['code'] ?? 0) === 202) {
                    $queued++;
                }
            }
        }

        return add_query_arg('polski_ksef_bulk', $queued, $redirect);
    }

    public function renderBulkNotice(): void
    {
        if (! isset($_GET['polski_ksef_bulk']) || ! current_user_can('manage_woocommerce')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a count shown back after the bulk action, nothing is changed.
            return;
        }
        $count = absint($_GET['polski_ksef_bulk']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
        printf(
            '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %d: number of invoices queued */
                _n('%d invoice queued for KSeF.', '%d invoices queued for KSeF.', $count, 'polski'),
                $count,
            )),
        );
    }

    // --- auto_send ---------------------------------------------------------

    /**
     * Queue a new invoice for KSeF when the store asked for that. Without a
     * certificate nothing happens: the invoice stays "not sent" for a manual
     * click once one is added.
     *
     * In an offline mode the invoice is issued offline first (frozen XML,
     * KOD I and KOD II on the PDF) and queued whatever auto_send says: the
     * deadline to send it is statutory. The PDF is rewritten with both codes
     * before InvoiceService returns, so a mail sent after the invoice was
     * created attaches that one. A correction whose original has no KSeF
     * number yet goes the online way and waits. Anything else that stops an
     * offline issue falls back to the online rules.
     */
    public function autoSend(mixed $invoice): void
    {
        if (! $invoice instanceof Invoice) {
            return;
        }

        $settings = get_option('polski_pro_ksef', []);
        $settings = is_array($settings) ? $settings : [];

        // Consumer invoices may stay outside KSeF (art. 106ga ust. 2 pkt 4): the shop chose PDF only.
        if (! empty($settings['consumers_outside']) && trim((string) $invoice->nipBuyer) === '') {
            return;
        }

        $mode = self::offlineModeFor($settings, $invoice->type, static fn (): bool => KSeFCredentials::usable('offline'));

        $code = $mode !== '' ? $this->sync->issueOffline($invoice->id, $mode)['code'] : 0;

        if ($code === 202) {
            return;
        }

        // A correction waiting for its original is queued and held whatever auto_send says.
        if ($code === 425) {
            $this->sync->queueForSending($invoice->id);
            return;
        }

        if (! self::shouldAutoSend($settings, $invoice->type, static fn (): bool => KSeFCredentials::load() !== null)) {
            return;
        }

        $this->sync->queueForSending($invoice->id);
    }

    /**
     * The offline mode a new invoice is issued in, '' for online: the outage
     * switch when it is on (as the Ministry announced it), else offline24
     * when the store chose it.
     *
     * @param array<mixed>     $settings              The polski_pro_ksef option.
     * @param \Closure(): bool $hasOfflineCredentials Only asked when everything else says yes (it decrypts the key).
     */
    public static function offlineModeFor(array $settings, InvoiceType $type, \Closure $hasOfflineCredentials): string
    {
        $kind = (string) ($settings['outage_kind'] ?? '');
        $mode = match (true) {
            ! empty($settings['outage_active']) && in_array($kind, ['offline', 'awaryjny'], true) => $kind,
            ($settings['offline_mode'] ?? '') === 'offline24' => 'offline24',
            default => '',
        };

        return $mode !== '' && ! empty($settings['enabled']) && $type->isAccountingDocument() && $hasOfflineCredentials() ? $mode : '';
    }

    /**
     * @param array<mixed>          $settings       The polski_pro_ksef option.
     * @param \Closure(): bool      $hasCredentials Only asked when everything else says yes (it decrypts the key).
     */
    public static function shouldAutoSend(array $settings, InvoiceType $type, \Closure $hasCredentials): bool
    {
        return ! empty($settings['enabled'])
            && ! empty($settings['auto_send'])
            && $type->isAccountingDocument()
            && $hasCredentials();
    }

    // --- metabox -----------------------------------------------------------

    public function addMetaBox(): void
    {
        $screens = ['shop_order'];

        if (function_exists('wc_get_page_screen_id')) {
            $screens[] = wc_get_page_screen_id('shop-order');
        }

        foreach (array_unique($screens) as $screen) {
            add_meta_box('polski-pro-ksef', __('KSeF', 'polski'), [$this, 'renderMetaBox'], $screen, 'side', 'default');
        }
    }

    public function enqueue(string $hook = ''): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = $screen instanceof \WP_Screen ? $screen->id : '';
        $screens = ['shop_order', 'edit-shop_order', function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'woocommerce_page_wc-orders'];

        if (! in_array($id, $screens, true)) {
            return;
        }

        wp_enqueue_style(
            'polski-pro-ksef-orders',
            plugins_url('assets/css/ksef-orders.css', \Polski\PLUGIN_FILE),
            [],
            \Polski\VERSION,
        );
    }

    public function renderMetaBox(mixed $postOrOrder): void
    {
        $order = $postOrOrder instanceof \WC_Order
            ? $postOrOrder
            : wc_get_order(is_object($postOrOrder) && isset($postOrOrder->ID) ? (int) $postOrOrder->ID : 0);

        if (! $order instanceof \WC_Order) {
            return;
        }

        $settings = get_option('polski_pro_ksef', []);
        $settings = is_array($settings) ? $settings : [];
        $enabled = ! empty($settings['enabled']);
        $environment = ($settings['environment'] ?? '') === 'production' ? 'production' : 'test';

        $all = $this->repository->findByOrder($order->get_id());
        $invoices = array_values(array_filter($all, static fn (Invoice $i): bool => $i->type->isAccountingDocument()));

        echo '<div class="polski-ksef-box">';

        if (! $enabled) {
            printf(
                '<p class="polski-ksef-hint">%s</p>',
                esc_html__('KSeF sending is switched off. Turn it on in the KSeF settings to send invoices from here.', 'polski'),
            );
        } elseif ($environment === 'test') {
            printf(
                '<p class="polski-ksef-hint">%s</p>',
                esc_html__('The store sends to the KSeF TEST environment. Invoices sent there have no legal effect.', 'polski'),
            );
        }

        if ($invoices === []) {
            printf('<p class="polski-ksef-empty">%s</p>', esc_html__('No VAT invoice for this order yet. Issue one in the Invoices box first.', 'polski'));
        }

        foreach ($invoices as $invoice) {
            $this->renderInvoice($invoice, $enabled, $environment);
        }

        // Correction invoices are issued by an invoicing plugin (Polski PRO) through this filter.
        if (has_filter('polski/ksef/create_correction')) {
            $this->renderRefunds($order, $invoices);
        }

        echo '</div>';
    }

    private function renderInvoice(Invoice $invoice, bool $enabled, string $environment): void
    {
        $status = (string) $invoice->ksefStatus;
        $reference = (string) $invoice->ksefReference;
        $recorded = (string) $invoice->ksefEnvironment;
        $hasUpo = $reference !== '' && $this->sync->upoXml($invoice->id) !== null;
        // Same reading as KSeFSyncService: filed where it went, or anywhere when that was never recorded.
        $filed = $reference !== '' && ($recorded === '' || $recorded === $environment);

        echo '<div class="polski-ksef-invoice">';
        printf(
            '<p class="polski-ksef-head"><strong>%1$s</strong> <span class="polski-ksef-type">%2$s</span></p>',
            esc_html($invoice->number),
            esc_html($invoice->type->label()),
        );

        echo '<p class="polski-ksef-status">';
        echo KSeFOrderColumn::badgeHtml($status, $recorded); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside badgeHtml().
        if ($recorded === 'production') {
            printf(' <span class="polski-ksef-env is-production">%s</span>', esc_html__('Production', 'polski'));
        }
        echo '</p>';

        if ($reference !== '') {
            printf(
                '<p class="polski-ksef-number"><span>%1$s</span> <code title="%2$s">%3$s</code></p>',
                esc_html($status === 'accepted' ? __('KSeF number', 'polski') : __('Reference', 'polski')),
                esc_attr__('Select to copy', 'polski'),
                esc_html($reference),
            );
        }

        if ((string) $invoice->ksefError !== '') {
            printf('<p class="polski-ksef-error" role="status">%s</p>', esc_html((string) $invoice->ksefError));
        }

        if ($invoice->ksefOfflineMode !== null && ! VerificationLink::isKsefNumber($reference)) {
            printf(
                '<p class="polski-ksef-offline">%s</p>',
                esc_html(sprintf(
                    /* translators: 1: offline mode (offline24, offline, awaryjny), 2: deadline date and time, or "not known yet". */
                    __('Issued offline (%1$s). Send to KSeF by: %2$s.', 'polski'),
                    $invoice->ksefOfflineMode,
                    $invoice->ksefOfflineDeadline !== null
                        ? wp_date((string) get_option('date_format', 'Y-m-d') . ' ' . (string) get_option('time_format', 'H:i'), $invoice->ksefOfflineDeadline->getTimestamp())
                        : __('not known until the outage ends', 'polski'),
                )),
            );
        }

        $buttons = [];

        if ($enabled && ! $filed) {
            $buttons[] = $this->button('send', $invoice->id, __('Send to KSeF', 'polski'), __('Queue this invoice for KSeF. It is sent in the background and the status updates here.', 'polski'), true);
        }

        if ($reference !== '' && (string) $invoice->ksefSession !== '' && ! ($status === 'accepted' && $hasUpo)) {
            $buttons[] = $this->button('refresh', $invoice->id, __('Refresh status', 'polski'), __('Ask KSeF now instead of waiting for the next automatic check, and fetch the UPO if it is ready.', 'polski'));
        }

        if (KSeFSyncService::correctable($invoice)) {
            $buttons[] = $this->button('technical', $invoice->id, __('Technical correction', 'polski'), __('Send this offline invoice again with the same content, rebuilt, and a reference to the rejected file (KSeF technical correction). Only for technical reasons such as schema or size, not for missing permissions or a change of content.', 'polski'), false, 0, __('Send a technical correction? The invoice is rebuilt with the same content and sent again with a reference to the rejected file. Its QR codes on the PDF are recomputed; KSeF links the old KOD I to the corrected invoice.', 'polski'));
        }

        $buttons[] = $this->button('xml', $invoice->id, __('Download XML', 'polski'), __('The FA(3) document as it goes, or went, to KSeF. For checking the data or sending it to an accountant.', 'polski'));

        if ($hasUpo) {
            $buttons[] = $this->button('upo', $invoice->id, __('Download UPO', 'polski'), __('The official receipt (UPO) KSeF issued for this invoice, as XML. Keep it with your records.', 'polski'));
        }

        $correctUrl = apply_filters('polski/ksef/correct_url', null, $invoice);
        $correctUrl = is_string($correctUrl) && $correctUrl !== '' ? $correctUrl : null;
        if ($correctUrl !== null) {
            $buttons[] = sprintf(
                '<a class="button button-small" href="%s" title="%s">%s</a>',
                esc_url($correctUrl),
                esc_attr__('Correct a VAT rate, the buyer, a line name, the date of supply or an amount without a refund.', 'polski'),
                esc_html__('Correct', 'polski'),
            );
        }

        // Download XML is always there, so the row always shows.
        echo '<p class="polski-ksef-actions">' . implode(' ', $buttons) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().

        echo '</div>';
    }

    /**
     * Refunds that have no correction invoice yet.
     *
     * @param list<Invoice> $invoices Accounting invoices of the order.
     */
    private function renderRefunds(\WC_Order $order, array $invoices): void
    {
        $corrected = [];
        $original = null;

        foreach ($invoices as $invoice) {
            if ($invoice->sourceRefundId !== null) {
                $corrected[$invoice->sourceRefundId] = true;
            }
            // findByOrder is newest first; the correction cites the newest VAT invoice.
            if ($original === null && $invoice->type === InvoiceType::FakturaVAT) {
                $original = $invoice;
            }
        }

        $open = [];
        foreach ($order->get_refunds() as $refund) {
            if ($refund instanceof \WC_Order_Refund && ! isset($corrected[$refund->get_id()])) {
                $open[] = $refund;
            }
        }

        if ($open === []) {
            return;
        }

        echo '<div class="polski-ksef-refunds">';
        printf('<p><strong>%s</strong></p>', esc_html__('Refunds without a correction invoice', 'polski'));

        foreach ($open as $refund) {
            echo '<p class="polski-ksef-refund">';
            printf(
                /* translators: 1: refund ID, 2: refunded amount with currency. */
                esc_html__('Refund #%1$d (%2$s)', 'polski'),
                (int) $refund->get_id(),
                esc_html(wp_strip_all_tags(wc_price(abs((float) $refund->get_total()), ['currency' => $order->get_currency()]))),
            );

            if ($original === null) {
                printf(' <span class="polski-ksef-hint">%s</span>', esc_html__('Issue the VAT invoice first; the correction refers to it.', 'polski'));
            } elseif ($refund->get_items(['line_item', 'shipping', 'fee']) === []) {
                // InvoiceDataBuilder builds correction lines from refunded items only.
                printf(' <span class="polski-ksef-hint">%s</span>', esc_html__('This refund is an amount only, with no items, so no correction can be built from it. Refund by item quantities instead.', 'polski'));
            } else {
                $button = $this->button(
                    'correction',
                    $original->id,
                    __('Create correction', 'polski'),
                    __('Issue a correction invoice for this refund. With automatic sending on, it goes to KSeF right after its invoice.', 'polski'),
                    false,
                    $refund->get_id(),
                );
                echo ' ' . $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- button() escapes every part (esc_attr, esc_url, esc_html).
            }
            echo '</p>';
        }

        echo '</div>';
    }

    /**
     * @param string $confirm Asked before the link is followed, for actions that cannot be taken back.
     */
    private function button(string $action, int $invoiceId, string $label, string $tip, bool $primary = false, int $refundId = 0, string $confirm = ''): string
    {
        $args = ['action' => 'polski_pro_ksef_' . $action, 'invoice_id' => $invoiceId];
        if ($refundId > 0) {
            $args['refund_id'] = $refundId;
        }

        return sprintf(
            '<a class="button%1$s tips" data-tip="%2$s" href="%3$s"%5$s>%4$s</a>',
            $primary ? ' button-primary' : '',
            esc_attr($tip),
            esc_url(wp_nonce_url(add_query_arg($args, admin_url('admin-post.php')), self::nonceAction($action, $invoiceId, $refundId))),
            esc_html($label),
            $confirm !== '' ? ' onclick="return window.confirm(this.dataset.confirm);" data-confirm="' . esc_attr($confirm) . '"' : '',
        );
    }

    public static function nonceAction(string $action, int $invoiceId, int $refundId = 0): string
    {
        return 'polski_pro_ksef_' . $action . '_' . $invoiceId . ($refundId > 0 ? '_' . $refundId : '');
    }

    // --- handlers ----------------------------------------------------------

    /**
     * Whether the current user may run $action on this invoice: capability
     * first, then the nonce bound to the action, invoice and refund.
     */
    public static function authorized(string $action, int $invoiceId, string $nonce, int $refundId = 0): bool
    {
        return in_array($action, self::ACTIONS, true)
            && $invoiceId > 0
            && current_user_can('manage_woocommerce')
            && $nonce !== ''
            && wp_verify_nonce($nonce, self::nonceAction($action, $invoiceId, $refundId)) !== false;
    }

    public function handleSend(): void
    {
        $invoice = $this->authorize('send');
        $result = $this->sync->queueForSending($invoice->id);

        $this->back($invoice, $result['code'] < 300 ? 'success' : 'error', $result['message']);
    }

    public function handleTechnical(): void
    {
        $invoice = $this->authorize('technical');
        $result = $this->sync->technicalCorrection($invoice->id);

        $this->back($invoice, $result['code'] < 300 ? 'success' : 'error', $result['message']);
    }

    public function handleRefresh(): void
    {
        $invoice = $this->authorize('refresh');
        $fresh = $this->sync->refreshStatus($invoice->id) ?? $invoice;

        if ((string) $fresh->ksefError !== '') {
            $this->back($fresh, 'warning', (string) $fresh->ksefError);
        }

        $this->back($fresh, 'success', sprintf(
            /* translators: %s: KSeF status label, for example Accepted. */
            __('KSeF status refreshed: %s.', 'polski'),
            KSeFOrderColumn::badge((string) $fresh->ksefStatus)['label'],
        ));
    }

    public function handleUpo(): void
    {
        $invoice = $this->authorize('upo');
        $upo = $this->sync->upoXml($invoice->id);

        if ($upo === null) {
            $this->back($invoice, 'error', __('KSeF has not issued a UPO for this invoice yet.', 'polski'));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::upoFilename($invoice) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $upo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the stored XML, streamed verbatim as a download.
        exit;
    }

    public function handleXml(): void
    {
        $invoice = $this->authorize('xml');
        // The file sent (or frozen for an offline invoice) when there is one, else built now.
        $xml = $this->repository->frozenXml($invoice->id)['xml'] ?? null;
        if ($xml === null) {
            try {
                $xml = $this->sync->documentXml($invoice, $invoice->originalInvoiceId !== null ? $this->repository->findById($invoice->originalInvoiceId) : null);
            } catch (\InvalidArgumentException $e) {
                $this->back($invoice, 'error', $e->getMessage());
            }
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name('FA3-' . $invoice->number . '.xml') . '"');
        header('X-Content-Type-Options: nosniff');
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the FA(3) XML, streamed verbatim as a download.
        exit;
    }

    public function handleCorrection(): void
    {
        $refundId = isset($_GET['refund_id']) ? absint($_GET['refund_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- part of the nonce action checked in authorize().
        $original = $this->authorize('correction', $refundId);

        if ($refundId <= 0) {
            $this->back($original, 'error', __('No refund was chosen.', 'polski'));
        }

        if ($this->repository->hasCorrection($original->id, true)) {
            $this->back($original, 'error', __('This invoice was already corrected without a refund (rate, buyer, lines or date), so a refund correction built from the order would start from the wrong lines. Issue this refund as a correction in KSeF by hand.', 'polski'));
        }

        /** @var Invoice|null $correction Issued by the plugin that owns correction invoices (Polski PRO). */
        $correction = apply_filters('polski/ksef/create_correction', null, $original->id, $original->orderId, $refundId);
        $correction = $correction instanceof Invoice ? $correction : null;

        if ($correction === null) {
            $this->back($original, 'error', __('The correction invoice could not be created. Check that the refund belongs to this order and lists refunded items, not an amount only.', 'polski'));
        }

        $this->back($original, 'success', sprintf(
            /* translators: %s: correction invoice number. */
            __('Correction invoice %s created.', 'polski'),
            $correction->number,
        ));
    }

    /** upo-<KSeF number>.xml, or the invoice number when it has none yet. */
    public static function upoFilename(Invoice $invoice): string
    {
        $name = (string) $invoice->ksefReference !== '' ? (string) $invoice->ksefReference : $invoice->number;
        $name = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');

        return 'upo-' . ($name !== '' ? $name : (string) $invoice->id) . '.xml';
    }

    private function authorize(string $action, int $refundId = 0): Invoice
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified in authorized().
        $invoiceId = isset($_GET['invoice_id']) ? absint($_GET['invoice_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce'])) : '';
        // phpcs:enable

        if (! self::authorized($action, $invoiceId, $nonce, $refundId)) {
            wp_die(esc_html__('You are not allowed to do this, or the link has expired. Go back to the order and try again.', 'polski'), '', ['response' => 403, 'back_link' => true]);
        }

        $invoice = $this->repository->findById($invoiceId);

        if ($invoice === null) {
            wp_die(esc_html__('Invoice not found.', 'polski'), '', ['response' => 404, 'back_link' => true]);
        }

        return $invoice;
    }

    /** Redirect to the order with a notice for this user. */
    private function back(Invoice $invoice, string $type, string $message): never
    {
        set_transient(self::noticeKey(), ['type' => $type, 'message' => $message], 60);

        $order = $invoice->orderId > 0 ? wc_get_order($invoice->orderId) : null;
        $fallback = $order instanceof \WC_Order ? $order->get_edit_order_url() : admin_url();

        wp_safe_redirect(wp_get_referer() ?: $fallback);
        exit;
    }

    public function renderNotice(): void
    {
        $notice = get_transient(self::noticeKey());

        if (! is_array($notice) || ! isset($notice['message'])) {
            return;
        }

        delete_transient(self::noticeKey());
        $type = in_array($notice['type'] ?? '', ['success', 'warning', 'error'], true) ? $notice['type'] : 'info';

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s</strong> %3$s</p></div>',
            esc_attr($type),
            esc_html__('KSeF:', 'polski'),
            esc_html((string) $notice['message']),
        );
    }

    private static function noticeKey(): string
    {
        return 'polski_pro_ksef_notice_' . get_current_user_id();
    }
}
