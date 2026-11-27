<?php

declare(strict_types=1);

namespace Polski\KSeF;

use Polski\Contract\HasHooks;
use Polski\KSeF\V2\ApiClient;
use Polski\KSeF\V2\KSeFCredentials;

/**
 * Native settings screen for KSeF (WooCommerce, KSeF).
 *
 * Built like InvoiceSettingsPage: a plain form posting to admin-post.php. It
 * holds the environment and the KSeF certificate with its private key, which
 * the merchant requests and downloads in Aplikacja Podatnika KSeF 2.0 (MCU
 * stopped issuing certificates at the end of January 2026).
 *
 * The private key and passphrase are write-only here. They are sealed on save
 * by KSeFCredentials and never printed back, not even masked: an empty field
 * means "keep what is stored".
 */
final class KSeFSettingsPage implements HasHooks
{
    public const SLUG = 'polski-pro-ksef-settings';
    private const ACTION = 'polski_pro_ksef_settings';
    private const OPTION = 'polski_pro_ksef';

    /** Days before the Offline certificate expires that the settings warn about it. */
    private const EXPIRY_WARNING_DAYS = 30;

    private const ATTENTION_TRANSIENT = 'polski_pro_ksef_offline_attention';

    /** Outage settings whose change moves the deadlines of unsent offline invoices. */
    public const OUTAGE_KEYS = ['outage_active', 'outage_kind', 'outage_start', 'outage_end'];

    public function __construct(private readonly ?KSeFSyncService $sync = null)
    {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_post_' . self::ACTION, [$this, 'handleSave']);
        add_action('admin_post_polski_pro_ksef_test', [$this, 'handleTest']);
        add_action('admin_notices', [$this, 'renderUpgradeNotice']);
        add_action('admin_notices', [$this, 'renderOfflineNotices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        // A tab next to the module settings under Polski > Settings.
        add_filter('polski/admin/settings_tabs', static fn (array $tabs): array => $tabs + [self::SLUG => __('KSeF', 'polski')]);
    }

    public function enqueue(string $hook = ''): void
    {
        if (! str_ends_with($hook, '_page_' . self::SLUG) && ! str_ends_with($hook, '_page_polski-pro-invoices')) {
            return;
        }

        wp_enqueue_style('polski-pro-ksef-orders', plugins_url('assets/css/ksef-orders.css', \Polski\PLUGIN_FILE), [], \Polski\VERSION);

        if (str_ends_with($hook, '_page_' . self::SLUG)) {
            wp_enqueue_script('polski-pro-ksef-credential-files', plugins_url('assets/js/ksef-credential-files.js', \Polski\PLUGIN_FILE), [], \Polski\VERSION, true);
        }
    }

    /**
     * One file picker for a certificate and its key: the browser reads each
     * file, tells the certificate from the key by its content (or, for binary
     * DER, by the file name) and fills the matching textarea. The input has no
     * name, so no file is uploaded; only the PEM text in the textareas is sent.
     */
    private function filePicker(string $prefix, string $certTarget, string $keyTarget, bool $hasCert, bool $hasKey): string
    {
        $id = $prefix . '_files';

        return sprintf(
            '<p class="polski-ksef-file-row"><label class="button button-primary" for="%1$s">%2$s</label>'
            . '<input type="file" multiple id="%1$s" class="polski-ksef-files screen-reader-text" accept=".crt,.cer,.pem,.der,.key,.p8" data-cert="%3$s" data-key="%4$s" data-error="%5$s" /></p>'
            . '<ul class="polski-ksef-file-list" aria-live="polite">'
            . '<li id="%3$s_state" class="%6$s" data-missing="%7$s" data-loaded="%8$s">%9$s</li>'
            . '<li id="%4$s_state" class="%10$s" data-missing="%11$s" data-loaded="%12$s">%13$s</li></ul>',
            esc_attr($id),
            esc_html__('Choose the files', 'polski'),
            esc_attr($certTarget),
            esc_attr($keyTarget),
            esc_attr__('Not a certificate or key in PEM or DER format:', 'polski'),
            $hasCert ? 'is-ok' : 'is-missing',
            esc_attr__('Certificate (.crt): not loaded yet. Download it in Aplikacja Podatnika KSeF, Certificates, Download.', 'polski'),
            /* translators: %s: file name. */
            esc_attr__('Certificate: %s', 'polski'),
            esc_html($hasCert ? __('Certificate: stored', 'polski') : __('Certificate (.crt): not loaded yet. Download it in Aplikacja Podatnika KSeF, Certificates, Download.', 'polski')),
            $hasKey ? 'is-ok' : 'is-missing',
            esc_attr__('Private key (.key): not loaded yet.', 'polski'),
            /* translators: %s: file name. */
            esc_attr__('Private key: %s', 'polski'),
            esc_html($hasKey ? __('Private key: stored (encrypted)', 'polski') : __('Private key (.key): not loaded yet.', 'polski')),
        );
    }

    /**
     * Offline mode warnings for whoever runs the shop: unsent offline invoices
     * due within a day or overdue, an Offline certificate about to expire, and
     * after KSeF stopped answering, the offer to switch the outage mode on.
     */
    public function renderOfflineNotices(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settingsUrl = admin_url('admin.php?page=' . self::SLUG . '#ksef-offline');
        // One query per admin page at most every five minutes, and none on a shop that never issued offline.
        $due = [];
        if ($this->sync !== null && get_option(KSeFCredentials::OFFLINE_OPTION)) {
            $due = get_transient(self::ATTENTION_TRANSIENT);
            if (! is_array($due)) {
                $due = $this->sync->offlineAttention();
                set_transient(self::ATTENTION_TRANSIENT, $due, 5 * MINUTE_IN_SECONDS);
            }
        }

        if ($due !== []) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('KSeF: offline invoices to send', 'polski') . '</strong> ';
            echo esc_html__('KSeF has not numbered these offline invoices and their deadline is within a day or past. Queued ones are sent again every hour; a rejected or failed one waits for you. Open the order to see the cause.', 'polski') . '</p><ul>';
            foreach ($due as $invoice) {
                $overdue = $invoice->ksefOfflineDeadline !== null && $invoice->ksefOfflineDeadline < new \DateTimeImmutable();
                printf(
                    '<li><a href="%1$s">%2$s</a> (%5$s): %3$s %4$s</li>',
                    esc_url($invoice->orderId > 0 ? admin_url('post.php?post=' . $invoice->orderId . '&action=edit') : admin_url('admin.php?page=polski-pro-invoices&ksef=offline_unsent')),
                    esc_html($invoice->number),
                    esc_html($overdue ? __('overdue since', 'polski') : __('due by', 'polski')),
                    esc_html($invoice->ksefOfflineDeadline !== null ? (string) wp_date((string) get_option('date_format', 'Y-m-d') . ' ' . (string) get_option('time_format', 'H:i'), $invoice->ksefOfflineDeadline->getTimestamp()) : ''),
                    esc_html(\Polski\KSeF\Admin\KSeFOrderColumn::badge((string) $invoice->ksefStatus)['label']),
                );
            }
            echo '</ul></div>';
        }

        // The login certificate: when it lapses, nothing reaches KSeF any more.
        $auth = KSeFCredentials::details('auth');
        if ($auth !== null && $auth['valid_to'] > 0 && $auth['valid_to'] < time() + self::EXPIRY_WARNING_DAYS * DAY_IN_SECONDS) {
            printf(
                '<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
                esc_html(sprintf(
                    /* translators: %s: expiry date of the KSeF login certificate. */
                    __('The KSeF certificate used to log in expires on %s. After that no invoice reaches KSeF; request a new one in Aplikacja Podatnika KSeF and load it in the KSeF settings.', 'polski'),
                    wp_date((string) get_option('date_format', 'Y-m-d'), $auth['valid_to']),
                )),
                esc_url(admin_url('admin.php?page=' . self::SLUG)),
                esc_html__('Open the KSeF settings', 'polski'),
            );
        }

        $offline = KSeFCredentials::details('offline');

        if ($offline !== null && $offline['valid_to'] > 0 && $offline['valid_to'] < time() + self::EXPIRY_WARNING_DAYS * DAY_IN_SECONDS) {
            printf(
                '<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
                esc_html(sprintf(
                    /* translators: %s: expiry date of the KSeF Offline certificate. */
                    __('The KSeF Offline certificate expires on %s. Without it no invoice can be issued offline; request a new one of type Offline and paste it in the KSeF settings.', 'polski'),
                    wp_date((string) get_option('date_format', 'Y-m-d'), $offline['valid_to']),
                )),
                esc_url($settingsUrl),
                esc_html__('Open the KSeF settings', 'polski'),
            );
        }

        $settings = get_option(self::OPTION, []);
        $hint = (int) get_option(KSeFSyncService::OUTAGE_HINT_OPTION, 0);

        if ($hint > time() - 2 * DAY_IN_SECONDS && $offline !== null && (! is_array($settings) || empty($settings['outage_active']))) {
            printf(
                '<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
                esc_html__('KSeF did not answer several sends in a row. If the Ministry of Finance announced that KSeF is unavailable or failing, switch on the outage mode: new invoices are then issued offline with both QR codes and get the statutory deadline.', 'polski'),
                esc_url($settingsUrl),
                esc_html__('Switch on the outage mode', 'polski'),
            );
        }
    }

    /**
     * After an upgrade from 1.x: the KSeF 1.0 token is gone and, on production,
     * auto_send was switched off (Migration_3_1_0). Shown until a certificate
     * is stored.
     */
    public function renderUpgradeNotice(): void
    {
        $notice = get_option('polski_pro_ksef_v2_upgrade');

        if (! is_array($notice) || ! current_user_can('manage_woocommerce') || KSeFCredentials::certificate() !== '') {
            return;
        }

        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('KSeF 2.0: a certificate is needed', 'polski') . '</strong></p><p>';
        echo esc_html__('Polski PRO now sends invoices to KSeF 2.0, which does not accept the KSeF 1.0 API token. The old token was removed and is not used. Request and download a KSeF certificate in the KSeF 2.0 Taxpayer Application (Aplikacja Podatnika KSeF 2.0) and add it with its private key.', 'polski');

        if (! empty($notice['auto_send_off'])) {
            echo ' ' . esc_html__('Automatic sending to the production environment was switched off, so nothing is filed before you have checked the setup. Turn it back on once an invoice went through.', 'polski');
        }

        printf(
            '</p><p><a class="button button-primary" href="%s">%s</a></p></div>',
            esc_url(admin_url('admin.php?page=' . self::SLUG)),
            esc_html__('Open the KSeF settings', 'polski'),
        );
    }

    public function registerPage(): void
    {
        add_submenu_page(
            'polski',
            __('KSeF', 'polski'),
            __('KSeF', 'polski'),
            'manage_woocommerce',
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = get_option(self::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $environment = ($settings['environment'] ?? '') === 'production' ? 'production' : 'test';
        $certificate = KSeFCredentials::certificate();
        $hasKey = KSeFCredentials::load() !== null;

        echo '<div class="wrap"><h1>Polski &rsaquo; ' . esc_html__('Settings', 'polski') . '</h1>';
        // Polski 1.52+ draws the Settings tab bar here; older versions just show the page.
        do_action('polski/admin/settings_nav', self::SLUG);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flash.
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'polski') . '</p></div>';
        }

        $error = isset($_GET['ksef_error']) ? get_transient('polski_pro_ksef_settings_error') : false;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if (is_string($error) && $error !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flash.
        if (isset($_GET['ksef_test']) && $_GET['ksef_test'] === 'ok') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Connected: KSeF accepted the certificate and issued an access token.', 'polski') . '</p></div>';
        }

        if ($certificate !== '' && ! $hasKey) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('The stored private key can no longer be decrypted, usually because the WordPress security salts changed. Paste the key and its passphrase again; nothing is sent to KSeF until then.', 'polski') . '</p></div>';
        }

        if (! self::saltsInConfig()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('SECURE_AUTH_KEY or SECURE_AUTH_SALT is not set in wp-config.php, so WordPress keeps that salt in the database, next to the encrypted private key. A database backup then contains everything needed to decrypt the key. Define both constants with unique values in wp-config.php, then paste the key again.', 'polski') . '</p></div>';
        }

        echo '<p>' . esc_html__('Invoices are sent to KSeF 2.0 with a KSeF certificate. Request and download one in the KSeF 2.0 Taxpayer Application (Aplikacja Podatnika KSeF 2.0), then paste the certificate and its private key here. The seller NIP from the invoice settings is the identity the shop authenticates as.', 'polski') . '</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '" />';
        wp_nonce_field(self::ACTION);
        echo '<table class="form-table">';

        echo '<tr><th scope="row">' . esc_html__('Sending', 'polski') . '</th><td>';
        printf(
            '<label><input type="checkbox" name="enabled" value="1"%s /> %s</label><br />',
            checked(! empty($settings['enabled']), true, false),
            esc_html__('Send invoices to KSeF', 'polski'),
        );
        printf(
            '<label><input type="checkbox" name="auto_send" value="1"%s /> %s</label>',
            checked(! empty($settings['auto_send']), true, false),
            esc_html__('Send each new invoice automatically, without a click on the order screen', 'polski'),
        );
        printf(
            '<br /><label><input type="checkbox" name="consumers_outside" value="1"%s /> %s</label><p class="description">%s</p>',
            checked(! empty($settings['consumers_outside']), true, false),
            esc_html__('Keep invoices to consumers outside KSeF', 'polski'),
            esc_html__('An invoice without a buyer tax ID is then issued as a PDF only and not sent automatically (art. 106ga ust. 2 pkt 4 of the VAT Act). It can still be sent from the order screen.', 'polski'),
        );
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ksef_environment">' . esc_html__('Environment', 'polski') . '</label></th><td>';
        echo '<select id="ksef_environment" name="environment">';
        printf('<option value="test"%s>%s</option>', selected($environment, 'test', false), esc_html__('Test (no legal effect)', 'polski'));
        printf('<option value="production"%s>%s</option>', selected($environment, 'production', false), esc_html__('Production (legally binding invoices)', 'polski'));
        echo '</select>';
        printf(
            '<p class="description">%s <code>%s</code></p>',
            esc_html__('Invoices are sent to', 'polski'),
            esc_html(ApiClient::BASE_URLS[$environment]),
        );
        echo '</td></tr>';

        $sellerNip = KSeFSyncService::configuredSellerNip();
        echo '<tr><th scope="row">' . esc_html__('Seller NIP', 'polski') . '</th><td>';
        echo $sellerNip !== ''
            ? '<code>' . esc_html($sellerNip) . '</code>'
            : '<strong>' . esc_html__('Not set or not a valid NIP. Invoices cannot be sent to KSeF until it is.', 'polski') . '</strong>';
        printf(
            '<p class="description">%s <a href="%s">%s</a></p>',
            esc_html__('The shop authenticates to KSeF as this NIP.', 'polski'),
            esc_url(admin_url('admin.php?page=polski&tab=setup#/setup-wizard')),
            esc_html__('Change it in the company details', 'polski'),
        );
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ksef_files">' . esc_html__('Certificate and private key', 'polski') . '</label></th><td>';
        echo '<p class="description">' . esc_html__('Aplikacja Podatnika KSeF gives two files: the certificate (.crt) and its private key (.key). Choose both at once; they stay in the browser until you save, then the key is stored encrypted.', 'polski') . '</p>';
        echo $this->filePicker('ksef', 'ksef_certificate', 'ksef_private_key', $certificate !== '', $hasKey); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
        echo $this->certificateSummary($certificate); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.
        echo '<details class="polski-ksef-paste"><summary>' . esc_html__('Paste the text instead', 'polski') . '</summary>';
        echo '<p><label for="ksef_certificate">' . esc_html__('Certificate (PEM)', 'polski') . '</label></p>';
        printf(
            '<textarea id="ksef_certificate" name="certificate" rows="5" class="large-text code" spellcheck="false">%s</textarea>',
            esc_textarea($certificate),
        );
        echo '<p><label for="ksef_private_key">' . esc_html__('Private key (PEM)', 'polski') . '</label></p>';
        printf(
            '<textarea id="ksef_private_key" name="private_key" rows="5" class="large-text code" spellcheck="false" autocomplete="off" placeholder="%s"></textarea>',
            esc_attr($hasKey
                ? __('A private key is stored. Leave empty to keep it, or paste a new one to replace it.', 'polski')
                : '-----BEGIN ENCRYPTED PRIVATE KEY-----'),
        );
        echo '</details>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ksef_passphrase">' . esc_html__('Private key passphrase', 'polski') . '</label></th><td>';
        echo '<input type="password" id="ksef_passphrase" name="passphrase" value="" class="regular-text" autocomplete="new-password" />';
        echo '<p class="description">' . esc_html__('The password set for the key when the certificate was requested. Only read together with a new private key.', 'polski') . '</p>';
        echo '</td></tr>';

        if (KSeFCredentials::usable('auth')) {
            echo '<tr><th scope="row">' . esc_html__('Connection', 'polski') . '</th><td>';
            printf(
                // A link, not a submit button: the form's own "action" field would win over the URL's in $_REQUEST.
                '<a class="button" href="%s">%s</a>',
                esc_url(wp_nonce_url(admin_url('admin-post.php?action=polski_pro_ksef_test'), 'polski_pro_ksef_test')),
                esc_html__('Test the connection to KSeF', 'polski'),
            );
            echo '<p class="description">' . esc_html__('Logs in to KSeF with the stored certificate. Nothing is sent and nothing is saved from the form.', 'polski') . '</p>';
            echo '</td></tr>';
        }

        if ($certificate !== '') {
            echo '<tr><th scope="row">' . esc_html__('Remove', 'polski') . '</th><td><label>';
            echo '<input type="checkbox" name="remove_credentials" value="1" /> ';
            echo esc_html__('Delete the stored certificate and private key', 'polski');
            echo '</label></td></tr>';
        }

        echo '</table>';
        $this->renderOffline($settings);
        submit_button();
        echo '</form></div>';
    }

    /**
     * "Offline mode": the Offline certificate (KOD II), offline24, and the
     * outage switch for when the Ministry announces KSeF unavailable or failing.
     *
     * @param array<mixed> $settings
     */
    private function renderOffline(array $settings): void
    {
        $certificate = KSeFCredentials::certificate('offline');
        $hasKey = KSeFCredentials::load('offline') !== null;
        $details = KSeFCredentials::details('offline');
        $usable = KSeFCredentials::usable('offline');

        echo '<h2 id="ksef-offline">' . esc_html__('Offline mode', 'polski') . '</h2>';
        echo '<p>' . esc_html__('An invoice issued offline gets its PDF with two QR codes at once: KOD I, to check the invoice in KSeF, and KOD II, signed with a KSeF certificate of type Offline, to confirm who issued it. It must reach KSeF by a statutory deadline; the shop sends it in the background straight away.', 'polski') . '</p>';
        echo '<table class="form-table polski-ksef-offline-table">';

        echo '<tr id="ksef-offline-certificate"><th scope="row"><label for="ksef_offline_files">' . esc_html__('Offline certificate and key', 'polski') . '</label></th><td>';
        echo '<p class="description" id="ksef_offline_certificate_help">' . esc_html__('A second certificate, of type Offline, with its own .crt and .key files (one KSeF certificate cannot do both). The person or entity in it needs the InvoiceWrite permission for the shop NIP.', 'polski') . '</p>';
        echo $this->filePicker('ksef_offline', 'ksef_offline_certificate', 'ksef_offline_private_key', $certificate !== '', $hasKey); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where built.

        if ($details !== null) {
            printf(
                '<p class="description">%s</p>',
                esc_html(sprintf(
                    /* translators: 1: certificate serial number, 2: expiry date. */
                    __('Type Offline, serial number %1$s, valid until %2$s.', 'polski'),
                    $details['serial'],
                    $details['valid_to'] > 0 ? wp_date((string) get_option('date_format', 'Y-m-d'), $details['valid_to']) : '?',
                )),
            );
        }

        echo '<details class="polski-ksef-paste"><summary>' . esc_html__('Paste the text instead', 'polski') . '</summary>';
        echo '<p><label for="ksef_offline_certificate">' . esc_html__('Offline certificate (PEM)', 'polski') . '</label></p>';
        printf(
            '<textarea id="ksef_offline_certificate" name="offline_certificate" rows="5" class="large-text code" spellcheck="false" aria-describedby="ksef_offline_certificate_help">%s</textarea>',
            esc_textarea($certificate),
        );
        echo '<p><label for="ksef_offline_private_key">' . esc_html__('Offline private key (PEM)', 'polski') . '</label></p>';
        printf(
            '<textarea id="ksef_offline_private_key" name="offline_private_key" rows="5" class="large-text code" spellcheck="false" autocomplete="off" placeholder="%s"></textarea>',
            esc_attr($hasKey
                ? __('A private key is stored. Leave empty to keep it, or paste a new one to replace it.', 'polski')
                : '-----BEGIN ENCRYPTED PRIVATE KEY-----'),
        );
        echo '</details>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ksef_offline_passphrase">' . esc_html__('Offline key passphrase', 'polski') . '</label></th><td>';
        echo '<input type="password" id="ksef_offline_passphrase" name="offline_passphrase" value="" class="regular-text" autocomplete="new-password" />';
        echo '</td></tr>';

        if ($certificate !== '') {
            echo '<tr><th scope="row">' . esc_html__('Remove', 'polski') . '</th><td><label>';
            echo '<input type="checkbox" name="remove_offline_credentials" value="1" /> ';
            echo esc_html__('Delete the stored Offline certificate and private key', 'polski');
            echo '</label></td></tr>';
        }

        echo '</table>';

        // Without an Offline certificate there is no KOD II, so the switches stay greyed out.
        printf('<fieldset class="polski-ksef-offline-modes"%s>', disabled($usable, false, false));
        echo '<legend class="screen-reader-text">' . esc_html__('Offline issuing', 'polski') . '</legend>';

        if (! $usable) {
            printf(
                '<p class="polski-ksef-hint">%1$s <a href="#ksef-offline-certificate">%2$s</a></p>',
                esc_html__('Add the Offline certificate first; offline issuing needs it for KOD II.', 'polski'),
                esc_html__('Go to the Offline certificate', 'polski'),
            );
        }

        echo '<table class="form-table">';
        $mode = ($settings['offline_mode'] ?? '') === 'offline24' ? 'offline24' : 'online';
        echo '<tr><th scope="row"><label for="ksef_offline_mode">' . esc_html__('Issuing mode', 'polski') . '</label></th><td>';
        echo '<select id="ksef_offline_mode" name="offline_mode">';
        printf('<option value="online"%s>%s</option>', selected($mode, 'online', false), esc_html__('Online: the invoice gets its KSeF number first', 'polski'));
        printf('<option value="offline24"%s>%s</option>', selected($mode, 'offline24', false), esc_html__('offline24: PDF with both QR codes at once, sent by the next working day', 'polski'));
        echo '</select>';
        echo '<p class="description">' . esc_html__('In offline24 every new invoice is issued offline and sent right away; the deadline is the safety net. Offline invoices are sent even when automatic sending is off.', 'polski') . '</p>';
        echo '</td></tr>';

        $kind = ($settings['outage_kind'] ?? '') === 'awaryjny' ? 'awaryjny' : 'offline';
        echo '<tr class="polski-ksef-outage"><th scope="row">' . esc_html__('KSeF outage announced by the Ministry', 'polski') . '</th><td>';
        printf(
            '<label><input type="checkbox" name="outage_active" value="1" class="polski-ksef-outage-toggle"%s /> %s</label>',
            checked(! empty($settings['outage_active']), true, false),
            esc_html__('Issue new invoices offline because KSeF is unavailable or failing', 'polski'),
        );
        echo '<div class="polski-ksef-outage-fields">';
        echo '<p><label for="ksef_outage_kind">' . esc_html__('Kind', 'polski') . '</label> <select id="ksef_outage_kind" name="outage_kind">';
        printf('<option value="offline"%s>%s</option>', selected($kind, 'offline', false), esc_html__('Unavailability (send by the next working day after it ends)', 'polski'));
        printf('<option value="awaryjny"%s>%s</option>', selected($kind, 'awaryjny', false), esc_html__('Failure (send within 7 working days after it ends)', 'polski'));
        echo '</select></p>';
        printf(
            '<p><label for="ksef_outage_start">%1$s</label> <input type="datetime-local" id="ksef_outage_start" name="outage_start" value="%2$s" /> <label for="ksef_outage_end">%3$s</label> <input type="datetime-local" id="ksef_outage_end" name="outage_end" value="%4$s" aria-describedby="ksef_outage_help" /></p>',
            esc_html__('Start', 'polski'),
            esc_attr((string) ($settings['outage_start'] ?? '')),
            esc_html__('End', 'polski'),
            esc_attr((string) ($settings['outage_end'] ?? '')),
        );
        echo '<p class="description" id="ksef_outage_help">' . esc_html__('Polish time, as in the announcement in BIP or the KSeF interface. KSeF offers no way to check this, so the shop never switches it on by itself. Leave the end empty until it is announced; changing it moves the deadlines of offline invoices not yet sent.', 'polski') . '</p>';
        echo '</div></td></tr>';
        echo '</table></fieldset>';
    }

    public function handleSave(): void
    {
        if (
            ! current_user_can('manage_woocommerce')
            || ! isset($_POST['_wpnonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['_wpnonce'])), self::ACTION)
        ) {
            wp_die(esc_html__('Invalid request.', 'polski'));
        }

        // Merge rather than replace: the option may carry keys this form does not own.
        $settings = get_option(self::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $environment = isset($_POST['environment']) ? sanitize_key(wp_unslash((string) $_POST['environment'])) : 'test';
        $settings['environment'] = $environment === 'production' ? 'production' : 'test';
        $settings['enabled'] = ! empty($_POST['enabled']);
        $settings['auto_send'] = ! empty($_POST['auto_send']);
        $settings['consumers_outside'] = ! empty($_POST['consumers_outside']);
        $outageBefore = $settings;
        // The offline switches sit in a disabled fieldset without a usable Offline
        // certificate and are not posted then; keep what is stored.
        if (KSeFCredentials::usable('offline')) {
            $settings = array_merge($settings, self::offlineSettings(wp_unslash($_POST))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is whitelisted or sanitised in offlineSettings().
        }
        update_option(self::OPTION, $settings);
        self::announceOutageChange($outageBefore, $settings);

        // PEM text is base64 between armour lines, and a passphrase is taken
        // verbatim: sanitize_text_field would collapse the line breaks and strip
        // characters a passphrase may legitimately contain. Neither is ever
        // printed; openssl parses both and rejects anything that is not a key.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $certificate = isset($_POST['certificate']) ? trim(wp_unslash((string) $_POST['certificate'])) : '';
        $privateKey = isset($_POST['private_key']) ? trim(wp_unslash((string) $_POST['private_key'])) : '';
        $passphrase = isset($_POST['passphrase']) ? wp_unslash((string) $_POST['passphrase']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $query = ['page' => self::SLUG, 'updated' => '1'];
        $query = self::saveOfflineCredentials($query);

        if (! empty($_POST['remove_credentials'])) {
            KSeFCredentials::clear();
            delete_option(ApiClient::TOKEN_OPTION);
        } elseif ($certificate === '' && $privateKey !== '') {
            set_transient('polski_pro_ksef_settings_error', __('Paste the certificate together with its private key. Nothing was stored.', 'polski'), 60);
            $query = ['page' => self::SLUG, 'ksef_error' => '1'];
        } elseif ($certificate !== '' && ($certificate !== KSeFCredentials::certificate() || $privateKey !== '')) {
            try {
                KSeFCredentials::save($certificate, $privateKey !== '' ? $privateKey : null, $passphrase);
                delete_option('polski_pro_ksef_v2_upgrade');
                // A new identity must not ride on the previous one's tokens.
                delete_option(ApiClient::TOKEN_OPTION);
            } catch (\InvalidArgumentException $e) {
                set_transient('polski_pro_ksef_settings_error', $e->getMessage(), 60);
                $query = ['page' => self::SLUG, 'ksef_error' => '1'];
            }
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
        exit;
    }

    /**
     * Offline mode and outage switch from the form; dates stay as the
     * datetime-local value (Warsaw time), anything unreadable becomes empty.
     *
     * @param array<mixed> $post
     * @return array{offline_mode: string, outage_active: bool, outage_kind: string, outage_start: string, outage_end: string}
     */
    public static function offlineSettings(array $post): array
    {
        $date = static function (mixed $value): string {
            $value = is_string($value) ? trim($value) : '';

            return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1 ? $value : '';
        };

        return [
            'offline_mode' => ($post['offline_mode'] ?? '') === 'offline24' ? 'offline24' : 'online',
            'outage_active' => ! empty($post['outage_active']),
            'outage_kind' => ($post['outage_kind'] ?? '') === 'awaryjny' ? 'awaryjny' : 'offline',
            'outage_start' => $date($post['outage_start'] ?? ''),
            'outage_end' => $date($post['outage_end'] ?? ''),
        ];
    }

    /**
     * Recompute offline deadlines when a save changed the outage dates or kind.
     *
     * @param array<mixed> $before The polski_pro_ksef option before the save.
     * @param array<mixed> $after  The option as saved.
     */
    public static function announceOutageChange(array $before, array $after): void
    {
        $keys = array_flip(self::OUTAGE_KEYS);
        $old = array_intersect_key($before, $keys);
        $new = array_intersect_key($after, $keys);
        ksort($old);
        ksort($new);

        if ($old !== $new) {
            do_action('polski_pro_ksef_outage_changed');
        }
    }

    /**
     * Store, replace or remove the Offline certificate; the same rules as the
     * authentication one, its own option.
     *
     * @param array<string, string> $query
     * @return array<string, string>
     */
    private static function saveOfflineCredentials(array $query): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in handleSave(); PEM and passphrase are taken verbatim, see there.
        $certificate = isset($_POST['offline_certificate']) ? trim(wp_unslash((string) $_POST['offline_certificate'])) : '';
        $privateKey = isset($_POST['offline_private_key']) ? trim(wp_unslash((string) $_POST['offline_private_key'])) : '';
        $passphrase = isset($_POST['offline_passphrase']) ? wp_unslash((string) $_POST['offline_passphrase']) : '';
        $remove = ! empty($_POST['remove_offline_credentials']);
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if ($remove) {
            KSeFCredentials::clear('offline');
            return $query;
        }

        if ($certificate === '' || ($certificate === KSeFCredentials::certificate('offline') && $privateKey === '')) {
            return $query;
        }

        try {
            KSeFCredentials::save($certificate, $privateKey !== '' ? $privateKey : null, $passphrase, 'offline');
        } catch (\InvalidArgumentException $e) {
            set_transient('polski_pro_ksef_settings_error', $e->getMessage(), 60);
            return ['page' => self::SLUG, 'ksef_error' => '1'];
        }

        return $query;
    }

    /** "Test the connection": authenticate with the stored certificate and report. */
    public function handleTest(): void
    {
        if (! current_user_can('manage_woocommerce') || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_GET['_wpnonce'] ?? ''))), 'polski_pro_ksef_test')) {
            wp_die(esc_html__('Invalid request.', 'polski'));
        }

        $client = ApiClient::fromSettings(KSeFSyncService::configuredSellerNip());
        $query = ['page' => self::SLUG, 'ksef_test' => 'ok'];

        try {
            if ($client === null) {
                throw new \RuntimeException(__('No usable certificate and key are stored.', 'polski'));
            }
            $client->accessToken();
        } catch (\Throwable $e) {
            /* translators: %s: error from KSeF or the connection */
            set_transient('polski_pro_ksef_settings_error', sprintf(__('The connection test failed: %s', 'polski'), $e->getMessage()), 60);
            $query = ['page' => self::SLUG, 'ksef_error' => '1'];
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
        exit;
    }

    /**
     * Whether wp_salt('secure_auth') comes from wp-config.php. When a constant
     * is missing or still the sample placeholder, wp_salt() generates the value
     * and stores it in the options table.
     */
    private static function saltsInConfig(): bool
    {
        foreach (['SECURE_AUTH_KEY', 'SECURE_AUTH_SALT'] as $name) {
            $value = defined($name) ? (string) constant($name) : '';

            if ($value === '' || $value === 'put your unique phrase here') {
                return false;
            }
        }

        return true;
    }

    /** Who the certificate names and until when, so an expiring one is noticed before invoices stop. */
    private function certificateSummary(string $certificate): string
    {
        if ($certificate === '') {
            return '';
        }

        $parsed = openssl_x509_parse($certificate);

        if (! is_array($parsed)) {
            return '';
        }

        $subject = is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [];
        $validTo = (int) ($parsed['validTo_time_t'] ?? 0);

        return sprintf(
            '<p class="description">%s</p>',
            esc_html(sprintf(
                /* translators: 1: certificate common name, 2: expiry date. */
                __('Issued to %1$s, valid until %2$s.', 'polski'),
                (string) ($subject['CN'] ?? $subject['O'] ?? '?'),
                $validTo > 0 ? wp_date((string) get_option('date_format', 'Y-m-d'), $validTo) : '?',
            )),
        );
    }
}
