<?php

declare(strict_types=1);

namespace Polski\Block;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Registers page-placed legal/informational blocks that wrap the matching
 * shortcodes (DSA report form, dispute-resolution notice, small-business VAT
 * notice, accepted payment methods). Each block renders through its shortcode,
 * which already self-skips when the relevant feature is not configured, so the
 * blocks are safe to register unconditionally and simply output nothing until
 * the store sets them up.
 *
 * Block metadata (icon, category, supports) comes from blocks/<slug>/block.json;
 * the translated title/description and the render callback are supplied here.
 */
final class LegalPageBlocks implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerBlocks']);
    }

    public function registerBlocks(): void
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/dsa-report', [
            'title' => __('DSA report form', 'polski'),
            'description' => __('A form for reporting illegal content under the Digital Services Act.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_dsa_report]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/complaints', [
            'title' => __('Dispute resolution notice', 'polski'),
            'description' => __('Displays your dispute resolution / online dispute resolution (ODR) information.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_complaints]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/small-business-notice', [
            'title' => __('Small business VAT notice', 'polski'),
            'description' => __('Displays the VAT-exempt small business notice when your store is configured as a small business.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_small_business_notice]'),
        ]);

        register_block_type(\Polski\PLUGIN_DIR . '/blocks/payment-methods', [
            'title' => __('Accepted payment methods', 'polski'),
            'description' => __('Lists the payment methods available in your store.', 'polski'),
            'render_callback' => static fn (): string => do_shortcode('[polski_payment_methods]'),
        ]);
    }
}
