<?php

declare(strict_types=1);

namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Admin\ModulesPage;
use Polski\Contract\HasHooks;

/**
 * Renders a ready-to-print complaint template (formularz reklamacyjny).
 *
 * Structure follows the expectations of the Polish Consumer Rights Act
 * and the commonly-used UOKiK template. The seller section is auto-
 * populated from `polski_general` (set by the setup wizard); the buyer
 * / product / defect / remedy sections are left blank for the customer
 * to complete.
 *
 * Three entry points:
 *   - shortcode `[polski_complaint_template]`
 *   - admin page preview + download (HTML)
 *   - REST endpoint for integrations
 */
final class ComplaintTemplateService implements HasHooks
{
    private const SHORTCODE = 'polski_complaint_template';

    public function registerHooks(): void
    {
        if (! ModulesPage::isModuleEnabled('complaint_template')) {
            return;
        }

        add_shortcode(self::SHORTCODE, [$this, 'renderShortcode']);
        add_action('admin_menu', [$this, 'registerPage'], 90);
        add_action('admin_post_polski_complaint_download', [$this, 'handleDownload']);
    }

    public function registerPage(): void
    {
        // Hidden (empty parent): routable by URL, surfaced via the Reports & Tools hub.
        add_submenu_page(
            '',
            __('Complaint template', 'polski'),
            __('Complaint template', 'polski'),
            'manage_woocommerce',
            'polski-complaint-template',
            [$this, 'renderAdminPage'],
        );
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Complaint template', 'polski') . '</h1>';
        echo '<p>' . esc_html__('Ready-to-print complaint form. The seller section is filled automatically from your setup wizard data; the buyer and defect sections stay blank for the customer.', 'polski') . '</p>';
        echo '<p><em>' . esc_html__('Disclaimer: this is a generic template. Consult a lawyer for shop-specific legal text.', 'polski') . '</em></p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('polski_complaint_download');
        echo '<input type="hidden" name="action" value="polski_complaint_download">';
        submit_button(__('Download as HTML', 'polski'), 'primary', 'submit', false);
        echo '</form>';

        echo '<hr>';
        echo '<h2>' . esc_html__('Preview', 'polski') . '</h2>';
        echo '<div style="max-width:800px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:4px">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML built from esc_html + inline tags.
        echo $this->buildHtml();
        echo '</div></div>';
    }

    public function handleDownload(): void
    {
        check_admin_referer('polski_complaint_download');

        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Insufficient permissions.', 'polski'));
        }

        $filename = 'polski-complaint-template-' . gmdate('Ymd') . '.html';
        $html = $this->buildStandalonePage();

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone HTML file, already escaped.
        echo $html;
        exit;
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode(array|string $atts = [], ?string $content = null, string $shortcodeTag = ''): string
    {
        return $this->buildHtml();
    }

    private function buildStandalonePage(): string
    {
        $title = esc_html__('Complaint form', 'polski');

        return '<!doctype html>'
            . '<html lang="pl"><head><meta charset="utf-8">'
            . '<title>' . $title . '</title>'
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:780px;margin:40px auto;padding:0 20px;color:#111;line-height:1.5}'
            . 'h1{font-size:24px;margin-bottom:8px}'
            . 'h2{font-size:16px;margin-top:24px}'
            . '.field{margin:8px 0;padding:6px 0;border-bottom:1px solid #999}'
            . '.row{display:flex;gap:24px}'
            . '.row .field{flex:1}'
            . '.small{font-size:12px;color:#666}'
            . '@media print{body{margin:0}.no-print{display:none}}'
            . '</style>'
            . '</head><body>'
            . $this->buildHtml()
            . '</body></html>';
    }

    private function buildHtml(): string
    {
        $seller = $this->loadSeller();
        $sellerBlock = '';

        if ($seller['name'] !== '') {
            $sellerBlock .= '<div>' . esc_html($seller['name']) . '</div>';
        }
        if ($seller['address'] !== '') {
            $sellerBlock .= '<div>' . esc_html($seller['address']) . '</div>';
        }
        if ($seller['nip'] !== '') {
            $sellerBlock .= '<div>' . esc_html__('NIP:', 'polski') . ' ' . esc_html($seller['nip']) . '</div>';
        }
        if ($seller['email'] !== '') {
            $sellerBlock .= '<div>' . esc_html__('Email:', 'polski') . ' ' . esc_html($seller['email']) . '</div>';
        }

        $html = '<article class="polski-complaint-template">';
        $html .= '<h1>' . esc_html__('Complaint form', 'polski') . '</h1>';
        $html .= '<p class="small">' . esc_html__('Submit this form within the statutory warranty period. The seller is required to respond within 14 days.', 'polski') . '</p>';

        $html .= '<h2>' . esc_html__('Seller', 'polski') . '</h2>';
        $html .= $sellerBlock !== '' ? $sellerBlock : '<div class="field">&nbsp;</div>';

        $html .= '<h2>' . esc_html__('Customer', 'polski') . '</h2>';
        $html .= '<div class="field"><strong>' . esc_html__('Name and surname:', 'polski') . '</strong></div>';
        $html .= '<div class="field"><strong>' . esc_html__('Address:', 'polski') . '</strong></div>';
        $html .= '<div class="row">';
        $html .= '<div class="field"><strong>' . esc_html__('Email:', 'polski') . '</strong></div>';
        $html .= '<div class="field"><strong>' . esc_html__('Phone:', 'polski') . '</strong></div>';
        $html .= '</div>';

        $html .= '<h2>' . esc_html__('Order and product', 'polski') . '</h2>';
        $html .= '<div class="row">';
        $html .= '<div class="field"><strong>' . esc_html__('Order number:', 'polski') . '</strong></div>';
        $html .= '<div class="field"><strong>' . esc_html__('Purchase date:', 'polski') . '</strong></div>';
        $html .= '</div>';
        $html .= '<div class="field"><strong>' . esc_html__('Product name:', 'polski') . '</strong></div>';

        $html .= '<h2>' . esc_html__('Defect / non-conformity', 'polski') . '</h2>';
        $html .= '<div class="field" style="min-height:72px"><strong>' . esc_html__('Description:', 'polski') . '</strong></div>';
        $html .= '<div class="field"><strong>' . esc_html__('Date of defect detection:', 'polski') . '</strong></div>';

        $html .= '<h2>' . esc_html__('Requested remedy', 'polski') . '</h2>';
        $html .= '<ul>';
        $html .= '<li>&#9744; ' . esc_html__('Repair', 'polski') . '</li>';
        $html .= '<li>&#9744; ' . esc_html__('Replacement', 'polski') . '</li>';
        $html .= '<li>&#9744; ' . esc_html__('Price reduction (specify amount)', 'polski') . '</li>';
        $html .= '<li>&#9744; ' . esc_html__('Withdrawal from the contract (refund of full price)', 'polski') . '</li>';
        $html .= '</ul>';

        $html .= '<h2>' . esc_html__('Bank account for refund', 'polski') . '</h2>';
        $html .= '<div class="field"><strong>' . esc_html__('Account number (IBAN):', 'polski') . '</strong></div>';

        $html .= '<p style="margin-top:32px" class="row">';
        $html .= '<span class="field" style="flex:1"><strong>' . esc_html__('Date:', 'polski') . '</strong></span>';
        $html .= '<span class="field" style="flex:2"><strong>' . esc_html__('Signature:', 'polski') . '</strong></span>';
        $html .= '</p>';

        $html .= '</article>';

        return $html;
    }

    /**
     * @return array{name: string, address: string, nip: string, email: string}
     */
    private function loadSeller(): array
    {
        $general = get_option('polski_general', []);

        if (! is_array($general)) {
            $general = [];
        }

        return [
            'name' => trim((string) ($general['company_name'] ?? '')),
            'address' => trim((string) ($general['company_address'] ?? '')),
            'nip' => trim((string) ($general['company_nip'] ?? '')),
            'email' => trim((string) ($general['company_email'] ?? '')),
        ];
    }
}
