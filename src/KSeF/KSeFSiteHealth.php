<?php

declare(strict_types=1);

namespace Polski\KSeF;

use Polski\Contract\HasHooks;
use Polski\KSeF\V2\ApiClient;
use Polski\KSeF\V2\KSeFCredentials;

defined('ABSPATH') || exit;

/**
 * KSeF checks in Tools > Site Health: the seller NIP, the login certificate
 * (present, readable, not expiring within 30 days) and whether the server
 * reaches the KSeF API of the chosen environment, through the public endpoint
 * that needs no login. Shown only once KSeF sending is switched on.
 *
 * existing-checked: Invoice/KSeF/KSeFSettingsPage.php - it renders the settings and admin notices; Site Health tests are a separate WordPress screen with their own result format, so they are kept apart.
 */
final class KSeFSiteHealth implements HasHooks
{
    private const EXPIRY_DAYS = 30;

    public function registerHooks(): void
    {
        add_filter('site_status_tests', [$this, 'tests']);
    }

    /**
     * @param array<string, mixed> $tests
     * @return array<string, mixed>
     */
    public function tests(array $tests): array
    {
        $settings = get_option('polski_pro_ksef', []);
        if (! is_array($settings) || empty($settings['enabled'])) {
            return $tests;
        }

        $tests['direct']['polski_pro_ksef_seller'] = ['label' => __('KSeF seller NIP', 'polski'), 'test' => [$this, 'testSeller']];
        $tests['direct']['polski_pro_ksef_certificate'] = ['label' => __('KSeF certificate', 'polski'), 'test' => [$this, 'testCertificate']];
        $tests['direct']['polski_pro_ksef_api'] = ['label' => __('KSeF API connection', 'polski'), 'test' => [$this, 'testApi']];

        return $tests;
    }

    /** @return array<string, mixed> */
    public function testSeller(): array
    {
        $nip = KSeFSyncService::configuredSellerNip();

        return $nip !== ''
            /* translators: %s: seller NIP */
            ? $this->result('good', sprintf(__('Invoices are sent to KSeF as NIP %s', 'polski'), $nip), '')
            : $this->result('critical', __('The seller NIP for KSeF is missing or invalid', 'polski'), __('Set a valid NIP in the invoice settings; until then no invoice can be sent to KSeF.', 'polski'));
    }

    /** @return array<string, mixed> */
    public function testCertificate(): array
    {
        $details = KSeFCredentials::details('auth');
        if ($details === null || ! KSeFCredentials::usable('auth')) {
            return $this->result('critical', __('No usable KSeF certificate', 'polski'), __('Load the certificate (.crt) and private key (.key) from Aplikacja Podatnika KSeF in Polski > Settings > KSeF.', 'polski'));
        }
        $date = $details['valid_to'] > 0 ? wp_date((string) get_option('date_format', 'Y-m-d'), $details['valid_to']) : '?';
        if ($details['valid_to'] > 0 && $details['valid_to'] < time() + self::EXPIRY_DAYS * DAY_IN_SECONDS) {
            /* translators: %s: expiry date */
            return $this->result('recommended', sprintf(__('The KSeF certificate expires on %s', 'polski'), $date), __('Request a new one in Aplikacja Podatnika KSeF before then, or invoices stop reaching KSeF.', 'polski'));
        }

        /* translators: %s: expiry date */
        return $this->result('good', sprintf(__('The KSeF certificate is valid until %s', 'polski'), $date), '');
    }

    /** @return array<string, mixed> */
    public function testApi(): array
    {
        $settings = get_option('polski_pro_ksef', []);
        $environment = is_array($settings) && ($settings['environment'] ?? '') === 'production' ? 'production' : 'test';
        $url = ApiClient::BASE_URLS[$environment] . '/security/public-key-certificates';
        $response = wp_remote_get($url, ['timeout' => 8]);
        $code = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            /* translators: %s: KSeF API address */
            return $this->result('good', __('The server reaches the KSeF API', 'polski'), sprintf(__('Checked %s.', 'polski'), ApiClient::BASE_URLS[$environment]));
        }

        $why = is_wp_error($response) ? $response->get_error_message() : sprintf('HTTP %d', $code);

        /* translators: 1: KSeF API address, 2: error */
        return $this->result('critical', __('The server cannot reach the KSeF API', 'polski'), sprintf(__('%1$s answered: %2$s. Ask the hosting provider to allow outgoing HTTPS to it.', 'polski'), ApiClient::BASE_URLS[$environment], $why));
    }

    /** @return array<string, mixed> */
    private function result(string $status, string $label, string $description): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'badge' => ['label' => 'KSeF', 'color' => $status === 'good' ? 'green' : ($status === 'critical' ? 'red' : 'orange')],
            'description' => $description !== '' ? '<p>' . esc_html($description) . '</p>' : '',
            'actions' => $status === 'good' ? '' : '<p><a href="' . esc_url(admin_url('admin.php?page=' . KSeFSettingsPage::SLUG)) . '">' . esc_html__('Open the KSeF settings', 'polski') . '</a></p>',
            'test' => 'polski_pro_ksef',
        ];
    }
}
