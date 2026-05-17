<?php

declare(strict_types=1);
namespace Polski\Service;

defined('ABSPATH') || exit;

use Polski\Contract\HasHooks;

/**
 * Registers three dynamic Gutenberg blocks that wrap the public withdrawal-flow
 * shortcodes so editors can insert them from the block inserter instead of
 * remembering the shortcode names. Server-render only (no JS bundle needed).
 *
 *   polski/withdrawal-lookup       → [polski_withdrawal_lookup]
 *   polski/withdrawal-info         → [polski_withdrawal_info]
 *   polski/withdrawal-form         → [polski_withdrawal_form_template]
 */
final class WithdrawalBlocksService implements HasHooks
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

        register_block_type('polski/withdrawal-lookup', [
            'title' => __('Polski — withdrawal lookup', 'polski'),
            'description' => __('Email + order number lookup form for guests to file a withdrawal.', 'polski'),
            'category' => 'widgets',
            'icon' => 'undo',
            'supports' => ['html' => false, 'align' => ['wide', 'full']],
            'render_callback' => static fn (): string => do_shortcode('[polski_withdrawal_lookup]'),
        ]);

        register_block_type('polski/withdrawal-info', [
            'title' => __('Polski — Annex I(A) information', 'polski'),
            'description' => __('Generated information about the consumer right of withdrawal (Annex I(A)).', 'polski'),
            'category' => 'widgets',
            'icon' => 'info',
            'supports' => ['html' => false, 'align' => ['wide', 'full']],
            'render_callback' => static fn (): string => do_shortcode('[polski_withdrawal_info]'),
        ]);

        register_block_type('polski/withdrawal-form', [
            'title' => __('Polski — Annex I(B) form template', 'polski'),
            'description' => __('The model withdrawal form template (Annex I(B)) pre-filled with merchant data.', 'polski'),
            'category' => 'widgets',
            'icon' => 'media-document',
            'supports' => ['html' => false, 'align' => ['wide', 'full']],
            'render_callback' => static fn (): string => do_shortcode('[polski_withdrawal_form_template]'),
        ]);
    }
}
