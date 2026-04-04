<?php
defined('ABSPATH') || exit;
/**
 * Quick view modal shell.
 *
 * @var array<string, mixed> $settings
 * @var string               $loading_text
 * @var bool                 $show_modal_label
 * @var bool                 $show_close_button
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-quick-view-modal" data-polski-quick-view-modal hidden>
    <div class="polski-quick-view-backdrop" data-polski-quick-view-backdrop></div>
    <div class="polski-quick-view-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr((string) ($settings['modal_title'] ?? __('Szybki podgląd produktu', 'polski'))); ?>">
        <?php if ($show_close_button) : ?>
            <button type="button" class="polski-quick-view-close" data-polski-quick-view-close aria-label="<?php echo esc_attr((string) ($settings['close_label'] ?? __('Zamknij', 'polski'))); ?>">
                ×
            </button>
        <?php endif; ?>
        <div class="polski-quick-view-content" data-polski-quick-view-content>
            <?php if ($show_modal_label) : ?>
                <p class="polski-quick-view-content__label"><?php echo esc_html((string) ($settings['modal_title'] ?? __('Szybki podgląd produktu', 'polski'))); ?></p>
            <?php endif; ?>
            <p><?php echo esc_html($loading_text); ?></p>
        </div>
    </div>
</div>
