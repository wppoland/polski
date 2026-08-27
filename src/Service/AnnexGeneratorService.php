<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Generates Annex I(A) (information on the right of withdrawal) and Annex I(B)
 * (model withdrawal form) content based on the merchant's data collected during
 * the setup wizard (option `polski_general`).
 *
 * Free tier outputs Polish text only. Multi-language generation is layered on top
 * of this service in the Pro plugin.
 *
 * Exposes two shortcodes:
 *   [polski_withdrawal_info]
 *   [polski_withdrawal_form_template]
 */
final class AnnexGeneratorService implements HasHooks
{
    public function registerHooks(): void
    {
        add_shortcode('polski_withdrawal_info', [$this, 'renderInfoShortcode']);
        add_shortcode('polski_withdrawal_form_template', [$this, 'renderFormShortcode']);
    }

    public function renderInfoShortcode(): string
    {
        $data = $this->merchantData();
        $days = $this->periodDays();

        $html = '<div class="polski-annex polski-annex--info">';
        $html .= '<h2>' . esc_html__('Right of withdrawal', 'polski') . '</h2>';

        $html .= '<h3>' . esc_html__('Right of withdrawal', 'polski') . '</h3>';
        $html .= '<p>' . sprintf(
            /* translators: %d: number of days */
            esc_html__('You have the right to withdraw from this contract within %d days without giving any reason.', 'polski'),
            (int) $days,
        ) . '</p>';
        $html .= '<p>' . sprintf(
            /* translators: %d: number of days */
            esc_html__('The withdrawal period will expire after %d days from the day on which you acquire, or a third party other than the carrier and indicated by you acquires, physical possession of the goods.', 'polski'),
            (int) $days,
        ) . '</p>';
        $html .= '<p>' . esc_html__('To exercise the right of withdrawal, you must inform us of your decision to withdraw from this contract by an unequivocal statement (for example a letter sent by post, fax or email).', 'polski') . '</p>';

        $html .= '<address style="font-style: normal;">';
        if ($data['name'] !== '') {
            $html .= '<strong>' . esc_html($data['name']) . '</strong><br />';
        }
        if ($data['address'] !== '') {
            $html .= nl2br(esc_html($data['address'])) . '<br />';
        }
        if ($data['phone'] !== '') {
            $html .= esc_html__('tel.', 'polski') . ' ' . esc_html($data['phone']) . '<br />';
        }
        if ($data['email'] !== '') {
            $html .= esc_html__('email:', 'polski') . ' ' . esc_html($data['email']) . '<br />';
        }
        if ($data['nip'] !== '') {
            $html .= esc_html__('NIP:', 'polski') . ' ' . esc_html($data['nip']);
        }
        $html .= '</address>';

        $html .= '<p>' . esc_html__('You may use the model withdrawal form, but it is not obligatory.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('To meet the withdrawal deadline, it is sufficient for you to send your communication concerning your exercise of the right of withdrawal before the withdrawal period has expired.', 'polski') . '</p>';

        $html .= '<h3>' . esc_html__('Effects of withdrawal', 'polski') . '</h3>';
        $html .= '<p>' . sprintf(
            /* translators: %d: number of days */
            esc_html__('If you withdraw from this contract, we shall reimburse to you all payments received from you, including the costs of delivery (with the exception of the supplementary costs resulting from your choice of a type of delivery other than the least expensive type of standard delivery offered by us), without undue delay and in any event not later than %d days from the day on which we are informed about your decision to withdraw from this contract.', 'polski'),
            (int) $days,
        ) . '</p>';
        $html .= '<p>' . esc_html__('We will carry out such reimbursement using the same means of payment as you used for the initial transaction, unless you have expressly agreed otherwise; in any event, you will not incur any fees as a result of such reimbursement.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('We may withhold reimbursement until we have received the goods back or you have supplied evidence of having sent back the goods, whichever is the earliest.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('You shall send back the goods or hand them over to us at the address above, without undue delay and in any event not later than 14 days from the day on which you communicate your withdrawal from this contract to us. The deadline is met if you send back the goods before the period of 14 days has expired.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('You will have to bear the direct cost of returning the goods.', 'polski') . '</p>';
        $html .= '<p>' . esc_html__('You are only liable for any diminished value of the goods resulting from the handling other than what is necessary to establish the nature, characteristics and functioning of the goods.', 'polski') . '</p>';

        $html .= '</div>';

        /**
         * Filter the generated Annex I(A) HTML.
         *
         * @param string                $html The generated HTML.
         * @param array<string, string> $data Merchant data.
         * @param int                   $days Withdrawal period in days.
         */
        return (string) apply_filters('polski/annex/info_html', $html, $data, $days);
    }

    public function renderFormShortcode(): string
    {
        $data = $this->merchantData();
        $lookupUrl = $this->lookupPageUrl();

        $html = '<div class="polski-annex polski-annex--form">';
        $html .= '<h2>' . esc_html__('Model withdrawal form', 'polski') . '</h2>';
        $html .= '<p><em>' . esc_html__('(complete and return this form only if you wish to withdraw from the contract)', 'polski') . '</em></p>';

        $html .= '<p>' . esc_html__('Addressee:', 'polski') . '<br />';
        if ($data['name'] !== '') {
            $html .= '<strong>' . esc_html($data['name']) . '</strong><br />';
        }
        if ($data['address'] !== '') {
            $html .= nl2br(esc_html($data['address'])) . '<br />';
        }
        if ($data['email'] !== '') {
            $html .= esc_html__('email:', 'polski') . ' ' . esc_html($data['email']);
        }
        $html .= '</p>';

        $html .= '<p>' . esc_html__('I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*)/for the provision of the following service (*):', 'polski') . '</p>';
        $html .= '<p><span style="display:inline-block;border-bottom:1px dotted #555;min-width:60%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Ordered on (*)/received on (*):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Name of consumer(s):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Address of consumer(s):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Signature of consumer(s) (only if this form is notified on paper):', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p>' . esc_html__('Date:', 'polski')
            . ' <span style="display:inline-block;border-bottom:1px dotted #555;min-width:40%;">&nbsp;</span></p>';
        $html .= '<p><em>' . esc_html__('(*) Delete as appropriate.', 'polski') . '</em></p>';

        if ($lookupUrl !== '') {
            /* translators: %s: lookup page URL where the consumer can file an online withdrawal declaration */
            $onlineLinkTemplate = __('You can also file the declaration online: <a href="%s">withdrawal form</a>.', 'polski');
            $html .= '<p>' . sprintf(
                wp_kses($onlineLinkTemplate, ['a' => ['href' => true]]),
                esc_url($lookupUrl),
            ) . '</p>';
        }

        $html .= '</div>';

        /**
         * Filter the generated Annex I(B) HTML.
         *
         * @param string                $html      The generated HTML.
         * @param array<string, string> $data      Merchant data.
         * @param string                $lookupUrl URL of the online lookup page (may be empty).
         */
        return (string) apply_filters('polski/annex/form_html', $html, $data, $lookupUrl);
    }

    /**
     * Render the Annex I(A) HTML, suitable for prefilling a page or capturing in an email.
     */
    public function getInfoHtml(): string
    {
        return $this->renderInfoShortcode();
    }

    /**
     * Render the Annex I(B) HTML.
     */
    public function getFormHtml(): string
    {
        return $this->renderFormShortcode();
    }

    /**
     * @return array{name: string, address: string, nip: string, regon: string, email: string, phone: string}
     */
    private function merchantData(): array
    {
        $general = get_option('polski_general', []);
        $general = is_array($general) ? $general : [];

        $data = [
            'name' => trim((string) ($general['company_name'] ?? get_bloginfo('name'))),
            'address' => trim((string) ($general['company_address'] ?? '')),
            'nip' => trim((string) ($general['company_nip'] ?? '')),
            'regon' => trim((string) ($general['company_regon'] ?? '')),
            // Only the merchant-configured public contact email; never fall back
            // to admin_email (this output is publicly rendered / publicly callable).
            'email' => trim((string) ($general['company_email'] ?? '')),
            'phone' => trim((string) ($general['company_phone'] ?? '')),
        ];

        if ($data['address'] === '') {
            $line = trim((string) get_option('woocommerce_store_address', ''));
            $line2 = trim((string) get_option('woocommerce_store_address_2', ''));
            $postcode = trim((string) get_option('woocommerce_store_postcode', ''));
            $city = trim((string) get_option('woocommerce_store_city', ''));

            $parts = array_filter([
                $line,
                $line2,
                trim($postcode . ' ' . $city),
            ]);
            $data['address'] = implode("\n", $parts);
        }

        /**
         * Filter merchant data used by the Annex generator.
         *
         * @param array{name: string, address: string, nip: string, regon: string, email: string, phone: string} $data
         */
        $filtered = apply_filters('polski/annex/merchant_data', $data);

        // Defend against bad filter implementations: ensure each expected key is a string.
        return [
            'name' => isset($filtered['name']) ? (string) $filtered['name'] : '',
            'address' => isset($filtered['address']) ? (string) $filtered['address'] : '',
            'nip' => isset($filtered['nip']) ? (string) $filtered['nip'] : '',
            'regon' => isset($filtered['regon']) ? (string) $filtered['regon'] : '',
            'email' => isset($filtered['email']) ? (string) $filtered['email'] : '',
            'phone' => isset($filtered['phone']) ? (string) $filtered['phone'] : '',
        ];
    }

    private function periodDays(): int
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $days = isset($settings['period_days']) ? (int) $settings['period_days'] : 14;

        return $days >= 1 ? $days : 14;
    }

    private function lookupPageUrl(): string
    {
        $settings = get_option('polski_withdrawal', []);
        $settings = is_array($settings) ? $settings : [];
        $pageId = isset($settings['lookup_page_id']) ? (int) $settings['lookup_page_id'] : 0;

        if ($pageId > 0) {
            $url = get_permalink($pageId);
            if (is_string($url)) {
                return $url;
            }
        }

        return '';
    }
}
