<?php

declare(strict_types=1);

namespace Polski\KSeF;

use Polski\Contract\HasHooks;
use Polski\KSeF\V2\ApiClient;
use Polski\KSeF\V2\FaMapper;
use Polski\KSeF\V2\FaParty;
use Polski\KSeF\V2\FaValidator;
use Polski\KSeF\V2\KSeFCredentials;
use Polski\KSeF\V2\KSeFCrypto;
use Polski\KSeF\V2\KSeFRateLimited;
use Polski\KSeF\V2\VerificationLink;
use Polski\KSeF\Model\Invoice;
use Polski\KSeF\Model\InvoiceType;
use Polski\KSeF\Repository\InvoiceRepository;

/**
 * Async KSeF 2.0 submission via WooCommerce Action Scheduler.
 *
 * One job builds the FA document, checks it against the schema, and sends it
 * through V2\ApiClient, which waits briefly for the verdict. A document still
 * pending, or accepted before its UPO exists, gets follow-up status jobs.
 *
 * What lands on the invoice row: ksef_reference holds the KSeF number once
 * accepted (the invoice reference inside the session while pending),
 * ksef_session the session it went in, ksef_environment the register it went
 * to, ksef_qr_url the KOD I link over the exact bytes sent, ksef_upo the
 * receipt, and ksef_error the last thing that went wrong, in words the
 * merchant can act on.
 *
 * Retries are bounded and counted in the job arguments, not in meta: invoices
 * live in their own table, so the post meta the 1.0 flow counted in belonged
 * to whichever post happened to share the invoice's id. A submission job also
 * carries the environment it was queued for, so switching the store from TEST
 * to production never sends a queued invoice somewhere nobody chose.
 *
 * Offline invoices (ksef-docs tryby-offline.md): issueOffline() builds and
 * freezes the XML when the invoice is issued, prints KOD I and KOD II over
 * it, and queues it. processSubmission() then sends those stored bytes with
 * offlineMode, so both codes keep describing what KSeF receives. An offline
 * invoice is never given up on: KSeF being down only moves the next try an
 * hour on, past the statutory deadline if need be, where its status becomes
 * offline_overdue.
 */
final class KSeFSyncService implements HasHooks
{
    /** Sends tried in total when KSeF cannot be reached or answers with an error. */
    private const MAX_ATTEMPTS = 4;

    /**
     * Further sends, OUTAGE_DELAY apart, when the last error was KSeF being
     * down (no answer, 408 or 5xx) rather than a refusal. KSeF outages last
     * hours, so after the quick retries an invoice keeps its place in the
     * queue for two days instead of ending as failed.
     */
    private const OUTAGE_RETRIES = 48;

    private const OUTAGE_DELAY = 3600;

    /** Follow-up status and UPO checks, CHECK_DELAY apart, before giving up. */
    private const MAX_CHECKS = 30;

    private const CHECK_DELAY = 120;

    /** How often a correction waits, CHECK_DELAY apart, for its original to be numbered. */
    private const MAX_HOLDS = 30;

    private const HOLD = 'hold';

    /** Leads the stored error of a 440 duplicate (handled as before, never by a technical correction). */
    public const DUPLICATE = '(440)';

    /** Daily WP-Cron mail about offline invoices close to or past their deadline. */
    public const DIGEST_HOOK = 'polski_pro_ksef_offline_digest';

    /** Set when an online invoice used up its quick retries because KSeF was down; the settings notice offers the outage switch. */
    public const OUTAGE_HINT_OPTION = 'polski_pro_ksef_outage_hint';

    /** @var \Closure(Invoice): ?string */
    private readonly \Closure $renderPdf;

    /** Set during refreshStatus(), which must not start a second chain of scheduled checks. */
    private bool $manual = false;

    /**
     * @param \Closure(string): ?ApiClient $clientFactory Client for a seller NIP; null when no certificate is configured.
     * @param (\Closure(Invoice): ?string)|null $renderPdf Writes the invoice PDF and returns its path; defaults to the polski/ksef/render_pdf filter (Polski PRO).
     */
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly FaMapper $mapper,
        private readonly FaValidator $validator,
        private readonly \Closure $clientFactory,
        ?\Closure $renderPdf = null,
    ) {
        // The PDF belongs to Polski PRO: it renders through this filter and returns the file path.
        $this->renderPdf = $renderPdf ?? static fn (Invoice $invoice): ?string => apply_filters('polski/ksef/render_pdf', null, $invoice);
    }

    public function registerHooks(): void
    {
        add_action('polski_pro_ksef_submit', [$this, 'processSubmission'], 10, 4);
        add_action('polski_pro_ksef_check_status', [$this, 'processStatusCheck'], 10, 2);
        add_action(self::DIGEST_HOOK, [$this, 'sendOfflineDigest']);
        add_action('polski_pro_ksef_outage_changed', [$this, 'recalculateDeadlines']);
        add_action('init', [$this, 'scheduleDigest']);
    }

    /** The daily digest only runs on a shop that can issue offline invoices. */
    public function scheduleDigest(): void
    {
        // Checked on admin and cron requests only, not on every storefront page.
        if (! is_admin() && ! wp_doing_cron()) {
            return;
        }

        if (get_option(KSeFCredentials::OFFLINE_OPTION) && ! wp_next_scheduled(self::DIGEST_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::DIGEST_HOOK);
        }
    }

    /**
     * Issue an invoice in an offline mode: build its XML once, freeze it, put
     * KOD I and KOD II over it on the PDF, set the deadline and queue it.
     *
     * A correction can only be offline once the corrected invoice has its
     * KSeF number, which its XML cites; until then it answers 425 and the
     * caller queues it the online way, where it waits for the original.
     *
     * @param string $mode One of OfflineDeadline::MODES.
     * @return array{code: int, message: string} code is an HTTP status: 202 when issued and queued.
     */
    public function issueOffline(int $invoiceId, string $mode): array
    {
        $invoice = $this->repository->findById($invoiceId);

        if ($invoice === null) {
            return ['code' => 404, 'message' => __('Invoice not found.', 'polski')];
        }

        $settings = get_option('polski_pro_ksef', []);
        $nip = $this->sellerNip($invoice);
        $environment = self::currentEnvironment();

        if (! in_array($mode, OfflineDeadline::MODES, true) || ! $invoice->type->isAccountingDocument()) {
            return ['code' => 422, 'message' => __('This document cannot be issued in KSeF offline mode.', 'polski')];
        }

        if (! is_array($settings) || empty($settings['enabled'])) {
            return ['code' => 409, 'message' => __('KSeF sending is switched off in the KSeF settings.', 'polski')];
        }

        if ($nip === '') {
            return ['code' => 422, 'message' => __('The seller NIP is missing or not ten digits. Set it in the invoice settings.', 'polski')];
        }

        $existing = $this->repository->frozenXml($invoiceId);

        if ($this->filedIn($invoice, $environment) || $existing !== null) {
            return ['code' => 409, 'message' => __('This invoice has already been issued for KSeF.', 'polski')];
        }

        if (! function_exists('as_schedule_single_action')) {
            return ['code' => 503, 'message' => __('Action Scheduler is unavailable, so the invoice cannot be queued.', 'polski')];
        }

        $corrected = null;

        if ($invoice->type === InvoiceType::Korygujaca) {
            $corrected = $invoice->originalInvoiceId !== null ? $this->repository->findById($invoice->originalInvoiceId) : null;

            if ($corrected === null || $this->originalBlocker($corrected, $environment) !== '') {
                return ['code' => 425, 'message' => __('The corrected invoice has no KSeF number yet, so this correction waits for it.', 'polski')];
            }
        }

        $codes = $this->offlineDocument($invoice, $nip, $corrected, $environment);

        if (is_string($codes)) {
            $this->fail($invoiceId, 'mapping_error', $codes);
            return ['code' => 422, 'message' => $codes];
        }

        $deadline = self::deadlineFor($mode, $codes['issued'], $settings);

        $stored = $this->repository->updateKSeF($invoiceId, '', self::offlineStatus($deadline), [
            'ksef_xml' => $codes['xml'],
            'ksef_offline_hash' => KSeFCrypto::hash($codes['xml']),
            'ksef_qr_url' => $codes['qr'],
            'ksef_cert_url' => $codes['cert'],
            'ksef_offline_mode' => $mode,
            'ksef_offline_deadline' => self::utc($deadline),
            'ksef_environment' => $environment,
            'ksef_error' => null,
        ]);

        if (! $stored || ($this->repository->frozenXml($invoiceId)['xml'] ?? '') !== $codes['xml']) {
            return ['code' => 500, 'message' => __('The invoice XML could not be stored, so it was not issued offline.', 'polski')];
        }

        // The PDF issued a moment ago has no codes; the buyer must get the one that has both.
        $this->regeneratePdf($invoiceId);
        $this->queueSubmission($invoiceId, 0, 0, $environment);

        return ['code' => 202, 'message' => __('Invoice issued in KSeF offline mode and queued for sending.', 'polski')];
    }

    /**
     * Technical correction of a rejected offline invoice
     * (offline/korekta-techniczna.md): the same content built again, which
     * gives a new DataWytworzeniaFa and so a new hash, sent with offlineMode
     * and hashOfCorrectedInvoice set to the first frozen document's hash.
     * KOD I and KOD II are recomputed over the new bytes, so the regenerated
     * PDF carries a matching pair; the buyer's old KOD I is redirected by KSeF.
     *
     * @return array{code: int, message: string}
     */
    public function technicalCorrection(int $invoiceId): array
    {
        $invoice = $this->repository->findById($invoiceId);
        $frozen = $this->repository->frozenXml($invoiceId);

        if ($invoice === null) {
            return ['code' => 404, 'message' => __('Invoice not found.', 'polski')];
        }

        if (! self::correctable($invoice) || $frozen === null) {
            return ['code' => 409, 'message' => __('A technical correction is only for an offline invoice that KSeF rejected.', 'polski')];
        }

        $nip = $this->sellerNip($invoice);
        $environment = (string) $invoice->ksefEnvironment !== '' ? (string) $invoice->ksefEnvironment : self::currentEnvironment();
        $corrected = $invoice->type === InvoiceType::Korygujaca && $invoice->originalInvoiceId !== null
            ? $this->repository->findById($invoice->originalInvoiceId)
            : null;

        if ($nip === '' || ($invoice->type === InvoiceType::Korygujaca && $corrected === null)) {
            return ['code' => 422, 'message' => __('The invoice can no longer be rebuilt: its seller NIP or the corrected invoice is missing.', 'polski')];
        }

        if (self::hasPendingSubmission($invoiceId)) {
            return ['code' => 409, 'message' => __('This invoice is already queued for KSeF.', 'polski')];
        }

        $codes = $this->offlineDocument($invoice, $nip, $corrected, $environment);

        if (is_string($codes)) {
            return ['code' => 422, 'message' => $codes];
        }

        // offline/korekta-techniczna.md: the content must not change. Shop
        // settings or the order may have changed since the invoice was issued.
        if (self::content($codes['xml']) !== self::content($frozen['xml'])) {
            return ['code' => 409, 'message' => __('Rebuilt today the invoice would differ from the rejected one (seller, buyer, lines or amounts changed since it was issued), and a technical correction may not change the content. Restore the old data or clarify the invoice in the KSeF Taxpayer Application.', 'polski')];
        }

        $stored = $this->repository->updateKSeF($invoiceId, '', self::offlineStatus($invoice->ksefOfflineDeadline), [
            'ksef_xml' => $codes['xml'],
            'ksef_qr_url' => $codes['qr'],
            'ksef_cert_url' => $codes['cert'],
            'ksef_error' => null,
        ]);

        if (! $stored || ($this->repository->frozenXml($invoiceId)['xml'] ?? '') !== $codes['xml']) {
            return ['code' => 500, 'message' => __('The rebuilt invoice XML could not be stored, so nothing was queued.', 'polski')];
        }

        $this->regeneratePdf($invoiceId);
        $this->queueSubmission($invoiceId, 0, 0, $environment);

        return ['code' => 202, 'message' => __('Technical correction queued: the invoice is sent again with the same content and a reference to the rejected file.', 'polski')];
    }

    /**
     * The XML of an offline invoice with KOD I and KOD II over it, or what is
     * wrong. KOD II is signed with the Offline certificate in the context of
     * the seller's own NIP, the identity the shop logs in as.
     *
     * @return array{xml: string, issued: \DateTimeImmutable, qr: string, cert: string}|string
     */
    private function offlineDocument(Invoice $invoice, string $nip, ?Invoice $corrected, string $environment): array|string
    {
        $credentials = KSeFCredentials::load('offline');
        $details = KSeFCredentials::details('offline');

        if ($credentials === null || $details === null || $details['serial'] === '' || ! KSeFCredentials::usable('offline')) {
            return __('No usable KSeF Offline certificate is stored, or it has expired. Add a valid one under WooCommerce, KSeF, Offline mode.', 'polski');
        }

        try {
            $xml = $this->documentXml($invoice, $corrected);
            $errors = $this->validator->errors($xml);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        $issued = self::issueDate($xml);
        $qr = self::verificationUrl($xml, $environment);

        if ($errors !== [] || $issued === null || $qr === null) {
            return $errors !== [] ? implode(' ', $errors) : __('The invoice XML has no seller NIP or issue date.', 'polski');
        }

        try {
            $cert = VerificationLink::certificateUrl($environment, 'Nip', $nip, $nip, $details['serial'], VerificationLink::hash($xml), $credentials['private_key']);
        } catch (\Throwable) {
            return __('KOD II could not be signed with the Offline certificate key.', 'polski');
        }

        return ['xml' => $xml, 'issued' => $issued, 'qr' => $qr, 'cert' => $cert];
    }

    /**
     * Deadline of an offline invoice under the current outage settings. The
     * outage end belongs to an invoice in "offline" mode; a failure counts for
     * any invoice still waiting when it was announced.
     *
     * @param array<mixed> $settings The polski_pro_ksef option.
     */
    public static function deadlineFor(string $mode, \DateTimeInterface $issued, array $settings, ?\Closure $holidays = null): ?\DateTimeImmutable
    {
        $kind = (string) ($settings['outage_kind'] ?? '');
        $start = self::settingDate((string) ($settings['outage_start'] ?? ''));
        $end = self::settingDate((string) ($settings['outage_end'] ?? ''));
        $base = OfflineDeadline::for($mode === 'awaryjny' ? 'offline' : $mode, $issued, $mode === 'offline' && $kind === 'offline' ? $end : null, null, $holidays);

        // A failure moves an offline24 or offline deadline only when it was
        // announced (start known) while that invoice was still within it.
        $announcedInWindow = $mode === 'awaryjny' || ($start !== null && ($base === null || $start <= $base));

        if ($kind !== 'awaryjny' || $end === null || $end < $issued || ! $announcedInWindow) {
            return $mode === 'awaryjny' ? null : $base;
        }

        return OfflineDeadline::for($mode, $issued, null, $end, $holidays);
    }

    /**
     * Deadlines of unsent offline invoices after the outage dates changed,
     * and the status that goes with them.
     */
    public function recalculateDeadlines(): void
    {
        $settings = get_option('polski_pro_ksef', []);
        $settings = is_array($settings) ? $settings : [];

        foreach ($this->repository->findOfflineUnsent(500) as $invoice) {
            $frozen = $this->repository->frozenXml($invoice->id);
            $issued = $frozen !== null ? self::issueDate($frozen['xml']) : null;

            if ($issued === null || $invoice->ksefOfflineMode === null) {
                continue;
            }

            // An invoice issued in an earlier outage keeps that outage's deadline.
            $start = self::settingDate((string) ($settings['outage_start'] ?? ''));
            if ($invoice->ksefOfflineMode !== 'offline24' && $start !== null && $issued < $start->setTime(0, 0)) {
                continue;
            }

            $deadline = self::deadlineFor($invoice->ksefOfflineMode, $issued, $settings);
            $status = in_array((string) $invoice->ksefStatus, ['offline', 'offline_overdue'], true) ? self::offlineStatus($deadline) : (string) $invoice->ksefStatus;
            $this->repository->updateKSeF($invoice->id, (string) $invoice->ksefReference, $status, ['ksef_offline_deadline' => self::utc($deadline)]);
        }
    }

    /**
     * Unsent offline invoices whose deadline is less than a day away or past.
     *
     * @return list<Invoice>
     */
    public function offlineAttention(): array
    {
        $soon = new \DateTimeImmutable('+1 day');

        return array_values(array_filter(
            $this->repository->findOfflineUnsent(500),
            static fn (Invoice $i): bool => $i->ksefOfflineDeadline !== null && $i->ksefOfflineDeadline <= $soon,
        ));
    }

    /**
     * Daily (WP-Cron): mark offline invoices past their deadline, and mail
     * the store's admin address the ones due within a day or overdue.
     */
    public function sendOfflineDigest(): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->repository->findOfflineUnsent(500) as $invoice) {
            if ($invoice->ksefStatus === 'offline' && $invoice->ksefOfflineDeadline !== null && $invoice->ksefOfflineDeadline < $now) {
                $this->repository->updateKSeF($invoice->id, (string) $invoice->ksefReference, 'offline_overdue');
            }
        }

        $due = $this->offlineAttention();

        if ($due === []) {
            return;
        }

        $lines = array_map(static fn (Invoice $i): string => sprintf(
            /* translators: 1: invoice number, 2: deadline date and time, 3: KSeF status, 4: link to the order or invoice list. */
            __('%1$s, deadline %2$s, status %3$s: %4$s', 'polski'),
            $i->number,
            $i->ksefOfflineDeadline !== null ? wp_date((string) get_option('date_format', 'Y-m-d') . ' ' . (string) get_option('time_format', 'H:i'), $i->ksefOfflineDeadline->getTimestamp()) : '?',
            (string) $i->ksefStatus,
            $i->orderId > 0 ? admin_url('post.php?post=' . $i->orderId . '&action=edit') : admin_url('admin.php?page=polski-pro-invoices&ksef=offline_unsent'),
        ), $due);

        wp_mail(
            (string) get_option('admin_email'),
            __('KSeF: offline invoices to send', 'polski'),
            __('These invoices were issued in KSeF offline mode and KSeF has not numbered them yet. The deadline is statutory. Queued ones are sent again every hour; a rejected or failed one waits for you: open the order to see the cause.', 'polski') . "\n\n" . implode("\n", $lines),
        );
    }

    /**
     * Queue an invoice for sending, after the checks a click on "send" needs.
     *
     * For the order screen and REST. Refuses when KSeF is switched off, no
     * certificate or seller NIP is set, the document is not an accounting one,
     * or it is already in KSeF of the environment the store is set to. An
     * invoice accepted on TEST can be sent again once the store is on production.
     *
     * @return array{code: int, message: string} code is an HTTP status: 202 when queued.
     */
    public function queueForSending(int $invoiceId): array
    {
        $invoice = $this->repository->findById($invoiceId);

        if ($invoice === null) {
            return ['code' => 404, 'message' => __('Invoice not found.', 'polski')];
        }

        if (! $invoice->type->isAccountingDocument()) {
            return ['code' => 422, 'message' => sprintf(
                /* translators: %s: invoice type label, for example Paragon. */
                __('%s documents are not eligible for KSeF submission.', 'polski'),
                $invoice->type->label(),
            )];
        }

        $settings = get_option('polski_pro_ksef', []);

        if (! is_array($settings) || empty($settings['enabled'])) {
            return ['code' => 409, 'message' => __('KSeF sending is switched off in the KSeF settings.', 'polski')];
        }

        $nip = $this->sellerNip($invoice);

        if ($nip === '') {
            return ['code' => 422, 'message' => __('The seller NIP is missing or not ten digits. Set it in the invoice settings.', 'polski')];
        }

        $client = ($this->clientFactory)($nip);

        if (! $client instanceof ApiClient) {
            return ['code' => 409, 'message' => __('No KSeF certificate is configured. Add it under WooCommerce, KSeF.', 'polski')];
        }

        // A submitted invoice can only be corrected, never withdrawn.
        if ($this->filedIn($invoice, $client->environment())) {
            return ['code' => 409, 'message' => __('This invoice has already been submitted to KSeF.', 'polski')];
        }

        // queueSubmission is a no-op without Action Scheduler.
        if (! function_exists('as_schedule_single_action')) {
            return ['code' => 503, 'message' => __('Action Scheduler is unavailable, so the invoice cannot be queued.', 'polski')];
        }

        // A second job would send the same document twice.
        if (self::hasPendingSubmission($invoiceId)) {
            return ['code' => 202, 'message' => __('This invoice is already queued for KSeF.', 'polski')];
        }

        $reference = (string) $invoice->ksefReference;
        $this->repository->updateKSeF($invoiceId, $reference, $reference === '' ? self::waitingStatus($invoice) : (string) $invoice->ksefStatus, ['ksef_error' => null]);
        $this->queueSubmission($invoiceId, 0, 0, $client->environment());

        return ['code' => 202, 'message' => __('Invoice queued for KSeF submission.', 'polski')];
    }

    /**
     * Ask KSeF about a sent invoice now, instead of waiting for the next
     * scheduled check, and fetch its UPO if it is missing. Scheduled checks
     * already queued carry on; this one schedules nothing.
     */
    public function refreshStatus(int $invoiceId): ?Invoice
    {
        $this->manual = true;

        try {
            $this->processStatusCheck($invoiceId, 0);
        } finally {
            $this->manual = false;
        }

        return $this->repository->findById($invoiceId);
    }

    /** The UPO XML KSeF issued for the invoice, or null before there is one. */
    public function upoXml(int $invoiceId): ?string
    {
        return $this->repository->ksefUpo($invoiceId);
    }

    /**
     * Queue an invoice for KSeF submission.
     *
     * @param string $environment 'test' or 'production' the job is for; '' takes the current setting.
     */
    public function queueSubmission(int $invoiceId, int $delaySeconds = 0, int $attempt = 0, string $environment = '', int $hold = 0): void
    {
        if (! function_exists('as_schedule_single_action')) {
            return;
        }

        as_schedule_single_action(
            time() + max(0, $delaySeconds),
            'polski_pro_ksef_submit',
            [
                'invoice_id' => $invoiceId,
                'attempt' => $attempt,
                'environment' => $environment !== '' ? $environment : self::currentEnvironment(),
                'hold' => $hold,
            ],
            'polski-pro-ksef',
        );
    }

    /** Whether a send job for this invoice is already waiting in Action Scheduler. */
    private static function hasPendingSubmission(int $invoiceId): bool
    {
        if (! function_exists('as_get_scheduled_actions')) {
            return false;
        }

        $pending = as_get_scheduled_actions([
            'hook' => 'polski_pro_ksef_submit',
            'group' => 'polski-pro-ksef',
            'status' => 'pending',
            'args' => ['invoice_id' => $invoiceId],
            'partial_args_matching' => 'json',
            'per_page' => 1,
        ], 'ids');

        return $pending !== [];
    }

    /**
     * Queue a status or UPO check for a submitted invoice.
     */
    public function queueStatusCheck(int $invoiceId, int $delaySeconds = self::CHECK_DELAY, int $check = 0): void
    {
        if ($this->manual || ! function_exists('as_schedule_single_action')) {
            return;
        }

        as_schedule_single_action(
            time() + $delaySeconds,
            'polski_pro_ksef_check_status',
            ['invoice_id' => $invoiceId, 'check' => $check],
            'polski-pro-ksef',
        );
    }

    /**
     * Build, validate and send one invoice (called by Action Scheduler).
     *
     * @param string $environment Where the job was queued for; '' for a job queued before jobs carried one.
     * @param int    $hold        Times a correction has already waited for its original.
     */
    public function processSubmission(int $invoiceId, int $attempt = 0, string $environment = '', int $hold = 0): void
    {
        $invoice = $this->repository->findById($invoiceId);
        $target = $environment !== '' ? $environment : self::currentEnvironment();

        // A submitted invoice can only be corrected, never withdrawn, so a
        // stray second job must not send it again, nor touch its row.
        if ($invoice === null || $this->filedIn($invoice, $target)) {
            return;
        }

        $nip = $this->sellerNip($invoice);

        if ($nip === '') {
            $this->fail($invoiceId, 'no_nip', __('The seller NIP is missing or not ten digits. Set it in the invoice settings.', 'polski'));
            return;
        }

        $client = ($this->clientFactory)($nip);

        if (! $client instanceof ApiClient) {
            $this->fail($invoiceId, 'no_credentials', __('No KSeF certificate is configured. Add it under WooCommerce, KSeF.', 'polski'));
            return;
        }

        $frozen = $this->repository->frozenXml($invoiceId);

        // The buyer's KOD I is a hash of the frozen bytes; bytes that no longer
        // match it (a column that cut or re-encoded them) must not be filed.
        if ($frozen !== null && ! str_ends_with((string) $invoice->ksefQrUrl, '/' . VerificationLink::hash($frozen['xml']))) {
            $this->fail($invoiceId, 'failed', __('The stored offline XML no longer matches the KOD I on the invoice, so it was not sent. Check the database column ksef_xml.', 'polski'));
            return;
        }

        // KOD I and KOD II of a frozen invoice name the QR host of the
        // register it was issued for; sending it to another would print a lie.
        if ($frozen !== null && (string) $invoice->ksefEnvironment !== '' && $invoice->ksefEnvironment !== $target) {
            $this->repository->updateKSeF($invoiceId, '', self::waitingStatus($invoice), [
                'ksef_error' => sprintf(
                    /* translators: 1: KSeF environment the offline invoice was issued for, 2: the one the job was for. */
                    __('This offline invoice was issued for KSeF %1$s and its QR codes point there, so it is not sent to %2$s. Switch the store back to %1$s to send it.', 'polski'),
                    (string) $invoice->ksefEnvironment,
                    $target,
                ),
            ]);
            return;
        }

        if ($client->environment() !== $target) {
            $reference = (string) $invoice->ksefReference;
            $this->repository->updateKSeF($invoiceId, $reference, $reference === '' ? self::waitingStatus($invoice) : (string) $invoice->ksefStatus, [
                'ksef_error' => sprintf(
                    /* translators: 1: KSeF environment the invoice was queued for, 2: the one the store is set to now. */
                    __('This invoice was queued for KSeF %1$s, but the store is now set to %2$s. Nothing was sent; send it again.', 'polski'),
                    $target,
                    $client->environment(),
                ),
            ]);
            return;
        }

        $corrected = null;

        if ($invoice->type === InvoiceType::Korygujaca) {
            $corrected = $invoice->originalInvoiceId !== null
                ? $this->repository->findById($invoice->originalInvoiceId)
                : null;

            if ($corrected === null) {
                $this->fail($invoiceId, 'missing_original_invoice', __('The invoice this one corrects no longer exists.', 'polski'));
                return;
            }

            $blocker = $this->originalBlocker($corrected, $target);

            if ($blocker === self::HOLD) {
                $this->holdCorrection($invoice, $attempt, $target, $hold, $corrected->ksefOfflineMode !== null);
                return;
            }

            if ($blocker !== '') {
                $this->fail($invoiceId, 'failed', $blocker);
                return;
            }
        }

        // tryby-offline.md, spec section 8: a correction held for its
        // original while the shop issues offline goes offline itself (frozen,
        // with KOD I and KOD II) as soon as the original has its number.
        if ($frozen === null && $corrected !== null) {
            $settings = get_option('polski_pro_ksef', []);
            $mode = \Polski\KSeF\Admin\KSeFOrderBox::offlineModeFor(is_array($settings) ? $settings : [], $invoice->type, static fn (): bool => KSeFCredentials::usable('offline'));

            if ($mode !== '' && $this->issueOffline($invoiceId, $mode)['code'] === 202) {
                return;
            }
        }

        // An offline invoice goes as the bytes its QR codes were computed over.
        try {
            $xml = $frozen['xml'] ?? $this->documentXml($invoice, $corrected);
            $errors = $frozen !== null ? [] : $this->validator->errors($xml);
        } catch (\InvalidArgumentException $e) {
            $xml = '';
            $errors = [$e->getMessage()];
        }

        if ($errors !== []) {
            $this->fail($invoiceId, 'mapping_error', implode(' ', $errors));
            return;
        }

        // Rebuilt after a rejection, the frozen XML no longer hashes to the
        // first one: that is a technical correction quoting the rejected file.
        $correcting = $frozen !== null && $frozen['hash'] !== '' && KSeFCrypto::hash($xml) !== $frozen['hash'] ? $frozen['hash'] : null;

        try {
            $result = $client->sendInvoice($xml, $frozen !== null, $correcting);
        } catch (KSeFRateLimited $e) {
            // Not a failed attempt: KSeF said when to come back.
            $this->repository->updateKSeF($invoiceId, '', self::waitingStatus($invoice), ['ksef_error' => self::waitMessage($e)]);
            $this->queueSubmission($invoiceId, max(1, $e->retryAfter), $attempt, $target, $hold);
            return;
        } catch (\Exception $e) {
            // 21166, "Korekta techniczna niedostepna" (api-changelog.md): retrying cannot help.
            if ($correcting !== null && str_contains($e->getMessage(), '21166')) {
                $this->repository->updateKSeF($invoiceId, '', 'rejected', ['ksef_error' => __('KSeF does not allow a technical correction of this invoice (21166). It cannot be fixed technically; clarify it in the KSeF Taxpayer Application (Aplikacja Podatnika KSeF).', 'polski') . ' ' . $e->getMessage()]);
                return;
            }

            // The answer may be lost after KSeF took the document; keeping the
            // session lets the retry's 440 prove the document is ours.
            $extra = $client->lastSession !== '' ? ['ksef_session' => $client->lastSession] : [];
            $this->retryOrFail($invoice, $attempt, $e->getMessage(), $target, $hold, $extra, self::isOutage($e));
            return;
        }

        do_action('polski/pro/ksef/submitted', $invoiceId, $result['invoice']);

        // KSeF now holds these exact bytes, so this is the one moment the KOD I
        // hash can be taken. A new send also replaces a receipt from TEST.
        $this->apply($invoiceId, $client, $result['session'], $result['invoice'], $result['status'], 0, (string) $invoice->ksefSession, [
            'ksef_environment' => $target,
            'ksef_qr_url' => self::verificationUrl($xml, $target),
            'ksef_upo' => null,
        ]);
    }

    /**
     * The FA(3) document for an invoice, as it would be sent. A buyer
     * correction also names the buyer as the corrected invoice did.
     *
     * @throws \InvalidArgumentException When the invoice cannot be mapped.
     */
    public function documentXml(Invoice $invoice, ?Invoice $corrected = null): string
    {
        return $this->mapper->toXml(
            $invoice,
            $this->seller($this->sellerNip($invoice)),
            self::buyerParty($invoice),
            $corrected,
            $corrected !== null && $invoice->correctionKind === 'buyer' ? self::buyerParty($corrected) : null,
        );
    }

    /**
     * Schema errors of a document, empty when KSeF would take it.
     *
     * @return list<string>
     */
    public function documentErrors(string $xml): array
    {
        return $this->validator->errors($xml);
    }

    /**
     * Follow up on a pending invoice, or fetch a missing UPO (called by Action Scheduler).
     */
    public function processStatusCheck(int $invoiceId, int $check = 0): void
    {
        $invoice = $this->repository->findById($invoiceId);

        if ($invoice === null) {
            return;
        }

        $session = (string) $invoice->ksefSession;
        $reference = (string) $invoice->ksefReference;

        if ($session === '' || $reference === '') {
            return;
        }

        $status = (string) $invoice->ksefStatus;

        if ($status === 'accepted' && $this->repository->ksefUpo($invoiceId) !== null) {
            return;
        }

        $client = ($this->clientFactory)($this->sellerNip($invoice));

        if (! $client instanceof ApiClient) {
            $this->repository->updateKSeF($invoiceId, $reference, $status, [
                'ksef_error' => __('No KSeF certificate is configured, so the status cannot be checked.', 'polski'),
            ]);
            return;
        }

        // The invoice lives in the register it was sent to; asking the other
        // one would only report it unknown.
        $recorded = (string) $invoice->ksefEnvironment;

        if ($recorded !== '' && $recorded !== $client->environment()) {
            $this->checkAgainOrStop($invoiceId, $check, $reference, $status, sprintf(
                /* translators: 1: KSeF environment the invoice was sent to, 2: the one the store is set to now. */
                __('This invoice was sent to KSeF %1$s, but the store is now set to %2$s, so its status cannot be checked.', 'polski'),
                $recorded,
                $client->environment(),
            ));
            return;
        }

        try {
            if ($status === 'accepted') {
                $this->repository->updateKSeF($invoiceId, $reference, 'accepted', [
                    'ksef_upo' => $client->upo($session, $reference),
                    'ksef_error' => null,
                ]);
                return;
            }

            $answer = $client->invoiceStatus($session, $reference);
        } catch (KSeFRateLimited $e) {
            $this->repository->updateKSeF($invoiceId, $reference, $status, ['ksef_error' => self::waitMessage($e)]);
            $this->queueStatusCheck($invoiceId, max(1, $e->retryAfter), $check);
            return;
        } catch (\Exception $e) {
            $this->checkAgainOrStop($invoiceId, $check, $reference, $status, $e->getMessage());
            return;
        }

        $this->apply($invoiceId, $client, $session, $reference, $answer, $check);
    }

    /**
     * Record what KSeF said about one document and schedule what is still owed.
     *
     * @param array<string, mixed> $status
     * @param string               $priorSession The session stored on the row before this send.
     * @param array{ksef_environment?: string, ksef_qr_url?: string|null, ksef_upo?: null} $extra Written with any outcome.
     */
    private function apply(int $invoiceId, ApiClient $client, string $session, string $reference, array $status, int $check, string $priorSession = '', array $extra = []): void
    {
        $code = ApiClient::statusCode($status);
        $ksefNumber = (string) ($status['ksefNumber'] ?? '');
        $row = $this->repository->findById($invoiceId);
        // An offline invoice's buyer already holds KOD I and KOD II over the
        // frozen bytes; nothing KSeF answers makes those codes untrue.
        $offline = $row !== null && $row->ksefOfflineMode !== null;
        $keepQr = $offline ? [] : ['ksef_qr_url' => null];

        // 440 is a duplicate: KSeF already holds a document with this seller
        // NIP, RodzajFaktury and P_2, and that is all it compares. It is ours
        // only when it went in the session this row already recorded (a send
        // whose answer was lost); anything else is a different document under
        // the same number, and taking its KSeF number would file a lie.
        if ($code === 440) {
            $extensions = is_array($status['status']['extensions'] ?? null) ? $status['status']['extensions'] : [];
            $original = (string) ($extensions['originalKsefNumber'] ?? '');
            $originalSession = (string) ($extensions['originalSessionReferenceNumber'] ?? '');

            if ($original !== '' && $priorSession !== '' && $priorSession === $originalSession) {
                $ksefNumber = $original;
                $session = $originalSession;
                $code = 200;
                // The link was computed over the duplicate's bytes, not the
                // original's; a frozen offline document is the same bytes.
                if (! $offline) {
                    $extra['ksef_qr_url'] = null;
                }
            } else {
                // Another runner may have filed this same row meanwhile; its
                // accepted result must not be overwritten by our duplicate.
                $current = $this->repository->findById($invoiceId);

                if ($current !== null && $current->ksefEnvironment === $client->environment() && VerificationLink::isKsefNumber((string) $current->ksefReference)) {
                    return;
                }

                // DUPLICATE marks the row: a duplicate is no technical error, so no technical correction is offered.
                $message = self::DUPLICATE . ' ' . sprintf(
                    /* translators: %s: KSeF number of the invoice already in KSeF. */
                    __('KSeF already holds another invoice from this seller with the same type and number (KSeF number %s). This invoice was not filed; give it a new number, or check that invoice in the KSeF application.', 'polski'),
                    $original !== '' ? $original : '?',
                );
                $this->repository->updateKSeF($invoiceId, '', 'rejected', array_merge($extra, [
                    'ksef_session' => $session,
                    'ksef_error' => $message,
                ], $keepQr));
                do_action('polski/pro/ksef/rejected', $invoiceId, $message);
                return;
            }
        }

        if ($code === 200 && $ksefNumber !== '') {
            $delay = self::CHECK_DELAY;

            try {
                $upo = $client->upo($session, $ksefNumber);
            } catch (KSeFRateLimited $e) {
                $upo = null;
                $delay = max(self::CHECK_DELAY, $e->retryAfter);
            } catch (\Exception) {
                $upo = null;
            }

            $this->repository->updateKSeF($invoiceId, $ksefNumber, 'accepted', array_merge($extra, [
                'ksef_session' => $session,
                'ksef_upo' => $upo,
                'ksef_error' => null,
            ]));
            do_action('polski/pro/ksef/accepted', $invoiceId);
            delete_option(self::OUTAGE_HINT_OPTION);

            // The PDF issued before this has neither the KSeF number nor KOD I.
            $this->regeneratePdf($invoiceId);

            if ($upo === null) {
                $this->checkAgainOrStop($invoiceId, $check, $ksefNumber, 'accepted', '', $delay);
            }

            return;
        }

        if ($code >= 400) {
            // Refused documents never entered KSeF, so the reference is cleared
            // and the merchant can send again once the cause is fixed.
            $message = sprintf(
                /* translators: 1: KSeF status code, 2: the reason in KSeF's words. */
                __('KSeF refused the invoice (%1$d): %2$s', 'polski'),
                $code,
                ApiClient::statusText($status),
            );

            if ($offline && str_contains(ApiClient::statusText($status), '21166')) {
                $message .= ' ' . __('KSeF does not allow a technical correction of this invoice (21166). It cannot be fixed technically; clarify it in the KSeF Taxpayer Application (Aplikacja Podatnika KSeF).', 'polski');
            } elseif ($offline) {
                $message .= ' ' . __('If the reason is technical (schema, size, duplicate), send a technical correction from the order. If it is missing permissions, have the person in the Offline certificate granted InvoiceWrite for the shop NIP and send the invoice again.', 'polski');
            }

            $this->repository->updateKSeF($invoiceId, '', 'rejected', array_merge($extra, [
                'ksef_session' => $session,
                'ksef_error' => $message,
            ], $keepQr));
            do_action('polski/pro/ksef/rejected', $invoiceId, $message);
            return;
        }

        $this->repository->updateKSeF($invoiceId, $reference, 'submitted', array_merge($extra, ['ksef_session' => $session, 'ksef_error' => null]));
        $this->checkAgainOrStop($invoiceId, $check, $reference, 'submitted', '');
    }

    private function checkAgainOrStop(int $invoiceId, int $check, string $reference, string $status, string $error, int $delay = self::CHECK_DELAY): void
    {
        if ($this->manual || $check + 1 < self::MAX_CHECKS) {
            if ($error !== '') {
                $this->repository->updateKSeF($invoiceId, $reference, $status, ['ksef_error' => $error]);
            }
            $this->queueStatusCheck($invoiceId, $delay, $check + 1);
            return;
        }

        // The reference stays, so nothing resends a document that may be in KSeF.
        $final = $status === 'accepted'
            ? __('The invoice is in KSeF, but its UPO could not be fetched. Download it in the KSeF application.', 'polski')
            : __('KSeF gave no final answer after an hour of checks. Look the invoice up in the KSeF application before sending it again.', 'polski');

        $this->repository->updateKSeF($invoiceId, $reference, $status, ['ksef_error' => trim($final . ' ' . $error)]);
    }

    /**
     * @param array<string, mixed> $extra Extra columns to store with the failure.
     */
    private function retryOrFail(Invoice $invoice, int $attempt, string $error, string $environment, int $hold, array $extra = [], bool $outage = false): void
    {
        $invoiceId = $invoice->id;
        $waiting = self::waitingStatus($invoice);

        if ($attempt + 1 < self::MAX_ATTEMPTS) {
            $this->repository->updateKSeF($invoiceId, '', $waiting, array_merge($extra, ['ksef_error' => $error]));
            $this->queueSubmission($invoiceId, (int) pow(2, $attempt) * 60, $attempt + 1, $environment, $hold);
            return;
        }

        // The offline deadline is statutory: a late send beats none, so an
        // offline invoice is tried every hour for as long as KSeF is down.
        if ($outage && $invoice->ksefOfflineMode !== null) {
            $this->repository->updateKSeF($invoiceId, '', $waiting, array_merge($extra, ['ksef_error' => trim($error . ' ' . __('KSeF looks unavailable, so this offline invoice is sent again every hour until KSeF takes it.', 'polski'))]));
            $this->queueSubmission($invoiceId, self::OUTAGE_DELAY, $attempt + 1, $environment, $hold);
            return;
        }

        if ($outage && $attempt + 1 < self::MAX_ATTEMPTS + self::OUTAGE_RETRIES) {
            if ($attempt + 1 === self::MAX_ATTEMPTS) {
                update_option(self::OUTAGE_HINT_OPTION, time(), false);
            }
            $this->repository->updateKSeF($invoiceId, '', $waiting, array_merge($extra, ['ksef_error' => trim($error . ' ' . __('KSeF looks unavailable, so the invoice stays queued and is sent again every hour for up to two days.', 'polski'))]));
            $this->queueSubmission($invoiceId, self::OUTAGE_DELAY, $attempt + 1, $environment, $hold);
            return;
        }

        $this->repository->updateKSeF($invoiceId, '', 'failed', array_merge($extra, ['ksef_error' => $error]));
        do_action('polski/pro/ksef/rejected', $invoiceId, $error);
    }

    /** KSeF did not answer, timed out or said it is down: worth asking again later, unlike a refusal. */
    private static function isOutage(\Exception $e): bool
    {
        $code = (int) $e->getCode();

        return $code === 408 || $code >= 500;
    }

    /**
     * Whether an offline invoice may get a technical correction: rejected by
     * KSeF (a failed send is simply sent again), but not as a 440 duplicate and not after KSeF answered 21166
     * (offline/korekta-techniczna.md, api-changelog.md).
     */
    public static function correctable(Invoice $invoice): bool
    {
        $error = (string) $invoice->ksefError;

        return $invoice->ksefOfflineMode !== null
            && (string) $invoice->ksefStatus === 'rejected'
            && ! str_starts_with($error, self::DUPLICATE)
            && ! str_contains($error, '21166');
    }

    /** What a row waiting for its send is called: offline invoices keep their offline status. */
    private static function waitingStatus(Invoice $invoice): string
    {
        return $invoice->ksefOfflineMode !== null ? self::offlineStatus($invoice->ksefOfflineDeadline) : 'queued';
    }

    private static function offlineStatus(?\DateTimeInterface $deadline): string
    {
        return $deadline !== null && $deadline < new \DateTimeImmutable() ? 'offline_overdue' : 'offline';
    }

    private static function utc(?\DateTimeInterface $moment): ?string
    {
        return $moment !== null ? \DateTimeImmutable::createFromInterface($moment)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
    }

    /** A date and time from the outage fields (datetime-local, Warsaw time); null when empty or unreadable. */
    private static function settingDate(string $value): ?\DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('Europe/Warsaw'));
        } catch (\Exception) {
            return null;
        }
    }

    private function fail(int $invoiceId, string $status, string $error): void
    {
        $this->repository->updateKSeF($invoiceId, '', $status, ['ksef_error' => $error]);
    }

    /**
     * Whether KSeF of $environment already has this invoice (numbered or
     * pending). A row from before ksef_environment existed counts as filed
     * wherever the store is now, the safe reading.
     */
    private function filedIn(Invoice $invoice, string $environment): bool
    {
        return (string) $invoice->ksefReference !== ''
            && ((string) $invoice->ksefEnvironment === '' || $invoice->ksefEnvironment === $environment);
    }

    /**
     * Why a correction cannot go yet: '' when it can, HOLD while the original
     * is on its way, else the message to fail it with.
     *
     * NrKSeFN (the original was never in KSeF) is only true for an invoice
     * issued outside KSeF. One refused or failed there is not that: it has to
     * be filed first. A number from the other environment does not count
     * either, or a production correction would cite a TEST invoice.
     */
    private function originalBlocker(Invoice $original, string $environment): string
    {
        $status = (string) $original->ksefStatus;
        $elsewhere = (string) $original->ksefEnvironment !== '' && $original->ksefEnvironment !== $environment;
        $numbered = VerificationLink::isKsefNumber((string) $original->ksefReference);

        if (($numbered || in_array($status, ['queued', 'submitted', 'offline', 'offline_overdue'], true)) && $elsewhere) {
            return sprintf(
                /* translators: 1: KSeF environment the corrected invoice went to, 2: the one this correction is for. */
                __('The corrected invoice was sent to KSeF %1$s only. Send it to KSeF %2$s first, then send this correction again.', 'polski'),
                (string) $original->ksefEnvironment,
                $environment,
            );
        }

        if ($numbered) {
            return '';
        }

        // An offline original is numbered once KSeF takes it; its correction waits (tryby-offline.md).
        if (in_array($status, ['queued', 'submitted', 'offline', 'offline_overdue'], true)) {
            return self::HOLD;
        }

        if (in_array($status, ['rejected', 'failed'], true)) {
            return __('The corrected invoice is not in KSeF: it was refused or could not be sent. File it first, then send this correction again.', 'polski');
        }

        return '';
    }

    /**
     * @param bool $patient The original is an offline invoice: it may take until its deadline, or past it, to be numbered.
     */
    private function holdCorrection(Invoice $invoice, int $attempt, string $environment, int $hold, bool $patient = false): void
    {
        if ($hold + 1 < self::MAX_HOLDS || $patient) {
            $this->repository->updateKSeF($invoice->id, '', self::waitingStatus($invoice), [
                'ksef_error' => __('Waiting for the corrected invoice to get its KSeF number; this correction goes right after.', 'polski'),
            ]);
            $this->queueSubmission($invoice->id, $hold + 1 < self::MAX_HOLDS ? self::CHECK_DELAY : self::OUTAGE_DELAY, $attempt, $environment, $hold + 1);
            return;
        }

        $this->fail($invoice->id, 'failed', __('The corrected invoice still had no KSeF number after an hour. Send this correction again once it has one.', 'polski'));
    }

    private function regeneratePdf(int $invoiceId): void
    {
        $invoice = $this->repository->findById($invoiceId);

        if ($invoice === null) {
            return;
        }

        try {
            $path = ($this->renderPdf)($invoice);
        } catch (\Throwable) {
            // The KSeF result is already stored; a PDF can be regenerated by hand.
            return;
        }

        if ($path !== null && $path !== '') {
            $this->repository->updatePdfPath($invoiceId, $path);
        }
    }

    /**
     * KOD I over the exact bytes sent, with the seller NIP and P_1 read back
     * from them, so the code can only describe the document KSeF received.
     */
    private static function verificationUrl(string $xml, string $environment): ?string
    {
        $xpath = self::xpath($xml);
        $nip = $xpath !== null ? trim((string) $xpath->evaluate('string(/*[local-name()="Faktura"]/*[local-name()="Podmiot1"]/*[local-name()="DaneIdentyfikacyjne"]/*[local-name()="NIP"])')) : '';
        $issued = self::issueDate($xml);

        return $nip !== '' && $issued !== null ? VerificationLink::url($environment, $issued, $nip, VerificationLink::hash($xml)) : null;
    }

    /** P_1 of the document, the only issue date KSeF reads (tryby-offline.md), as a Warsaw calendar day. */
    private static function issueDate(string $xml): ?\DateTimeImmutable
    {
        $xpath = self::xpath($xml);
        $issued = $xpath !== null
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $xpath->evaluate('string(/*[local-name()="Faktura"]/*[local-name()="Fa"]/*[local-name()="P_1"])')), new \DateTimeZone('Europe/Warsaw'))
            : false;

        return $issued !== false ? $issued : null;
    }

    /**
     * What a technical correction may not change: the text of the seller,
     * the buyer and the invoice body, whitespace-normalised. The header with
     * DataWytworzeniaFa is left out, and so is element naming, which is what a
     * schema fix changes.
     */
    private static function content(string $xml): string
    {
        $xpath = self::xpath($xml);

        if ($xpath === null) {
            return '';
        }

        $parts = [];
        foreach (['Podmiot1', 'Podmiot2', 'Fa'] as $name) {
            $texts = $xpath->query('/*[local-name()="Faktura"]/*[local-name()="' . $name . '"]//text()');
            foreach ($texts !== false ? $texts : [] as $text) {
                $value = trim((string) $text->nodeValue);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
            $parts[] = '|';
        }

        return implode("\n", $parts);
    }

    private static function xpath(string $xml): ?\DOMXPath
    {
        $dom = new \DOMDocument();

        return $xml !== '' && $dom->loadXML($xml) ? new \DOMXPath($dom) : null;
    }

    private static function waitMessage(KSeFRateLimited $e): string
    {
        return sprintf(
            /* translators: %d: seconds KSeF asked to wait. */
            __('KSeF limits how often it may be asked and asked to wait %d seconds. Trying again then.', 'polski'),
            $e->retryAfter,
        );
    }

    /** Same rule as ApiClient: only the exact "production" leaves TEST. */
    private static function currentEnvironment(): string
    {
        $settings = get_option('polski_pro_ksef', []);

        return is_array($settings) && ($settings['environment'] ?? '') === 'production' ? 'production' : 'test';
    }

    /**
     * The NIP the invoice was issued under, else the configured one; '' when
     * neither is a valid NIP. It is also the identity the session
     * authenticates as, and KSeF refuses a document whose seller is someone
     * else.
     */
    private function sellerNip(Invoice $invoice): string
    {
        return self::firstValidNip([(string) $invoice->nipSeller, self::configuredSellerNip()]);
    }

    /**
     * The shop's seller NIP, the one source for every PRO document that names
     * the seller (invoices, KSeF auth, JPK_FA, legal texts, withdrawal annex
     * and PDF): the invoice settings value, else the legacy
     * polski_pro_seller_nip option (nothing writes it any more), else the
     * company NIP from the free plugin's general settings. '' when none is a
     * valid NIP.
     */
    public static function configuredSellerNip(): string
    {
        $settings = get_option('polski_pro_invoices', []);

        return self::firstValidNip([
            is_array($settings) ? (string) ($settings['seller_nip'] ?? '') : '',
            (string) get_option('polski_pro_seller_nip', ''),
            self::companyNip(),
        ]);
    }

    /** The free plugin's company NIP (polski_general), normalised; '' when it is not a valid NIP. */
    public static function companyNip(): string
    {
        $general = get_option('polski_general', []);

        return self::firstValidNip([is_array($general) ? (string) ($general['company_nip'] ?? '') : '']);
    }

    /** @param list<string> $candidates The first valid NIP wins, normalised; '' when none is. */
    private static function firstValidNip(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $nip = self::normaliseNip($candidate);

            if (preg_match('/^[1-9]((\d[1-9])|([1-9]\d))\d{7}$/', $nip) === 1) {
                return $nip;
            }
        }

        return '';
    }

    /**
     * The seller: Polski PRO's invoice settings, else the company details of
     * this plugin (the same the free invoice prints), else the WooCommerce
     * store address. Same order as the PDF, so both documents name one seller.
     */
    private function seller(string $nip): FaParty
    {
        $settings = get_option('polski_pro_invoices', []);
        $settings = is_array($settings) ? $settings : [];
        $general = get_option('polski_general', []);
        $general = is_array($general) ? $general : [];
        $get = static fn (string $key, string $fallback): string => trim((string) ($settings[$key] ?? '')) !== ''
            ? trim((string) $settings[$key])
            : $fallback;

        // The free company address is one free-text field: its first line is the street, the rest the town.
        $company = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($general['company_address'] ?? '')) ?: [])));
        $store = (string) get_option('woocommerce_store_address', '');
        $storeTown = trim((string) get_option('woocommerce_store_postcode', '') . ' ' . (string) get_option('woocommerce_store_city', ''));

        return new FaParty(
            $nip,
            $get('seller_name', trim((string) ($general['company_name'] ?? '')) ?: (string) get_bloginfo('name')),
            'PL',
            $get('seller_address', $store !== '' ? $store : ($company[0] ?? '')),
            trim($get('seller_postcode', '') . ' ' . $get('seller_city', '')) ?: ($store !== '' ? $storeTown : implode(', ', array_slice($company, 1))),
        );
    }

    /**
     * The buyer snapshot on the invoice, filled in from the order where it is blank.
     *
     * @throws \InvalidArgumentException When name or address is blank.
     */
    public static function buyerParty(Invoice $invoice): FaParty
    {
        $order = $invoice->orderId > 0 ? wc_get_order($invoice->orderId) : null;
        $order = $order instanceof \WC_Order ? $order : null;

        $pick = static fn (?string $snapshot, string $fallback): string => trim((string) $snapshot) !== ''
            ? trim((string) $snapshot)
            : trim($fallback);

        $name = $pick($invoice->buyerName, $order !== null
            ? ($order->get_billing_company() !== '' ? $order->get_billing_company() : $order->get_formatted_billing_full_name())
            : '');
        $address = $pick($invoice->buyerAddress, $order !== null
            ? $order->get_billing_address_1() . ' ' . $order->get_billing_address_2()
            : '');
        $postcode = $pick($invoice->buyerPostcode, $order?->get_billing_postcode() ?? '');
        $city = $pick($invoice->buyerCity, $order?->get_billing_city() ?? '');
        $country = $order !== null && $order->get_billing_country() !== '' ? $order->get_billing_country() : 'PL';

        return new FaParty(
            self::normaliseNip((string) $invoice->nipBuyer),
            $name,
            $country,
            $address,
            trim($postcode . ' ' . $city),
        );
    }

    /** Drops spaces, dashes and a PL prefix; a foreign VAT id keeps its letters and so stays foreign. */
    private static function normaliseNip(string $nip): string
    {
        return (string) preg_replace('/^PL|[\s-]/i', '', trim($nip));
    }
}
