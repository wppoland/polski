<?php
/**
 * Quick view modal shell.
 *
 * @var array<string, mixed> $polski_settings
 * @var string               $polski_loading_text
 * @var bool                 $polski_show_modal_label
 * @var bool                 $polski_show_close_button
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-quick-view-modal" data-polski-quick-view-modal hidden>
    <div class="polski-quick-view-backdrop" data-polski-quick-view-backdrop></div>
    <div class="polski-quick-view-dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr((string) ($polski_settings['modal_title'] ?? __('Szybki podgląd produktu', 'polski'))); ?>">
        <?php if ($polski_show_close_button) : ?>
            <button type="button" class="polski-quick-view-close" data-polski-quick-view-close aria-label="<?php echo esc_attr((string) ($polski_settings['close_label'] ?? __('Zamknij', 'polski'))); ?>">
                ×
            </button>
        <?php endif; ?>
        <div class="polski-quick-view-content" data-polski-quick-view-content>
            <?php if ($polski_show_modal_label) : ?>
                <p class="polski-quick-view-content__label"><?php echo esc_html((string) ($polski_settings['modal_title'] ?? __('Szybki podgląd produktu', 'polski'))); ?></p>
            <?php endif; ?>
            <p><?php echo esc_html($polski_loading_text); ?></p>
        </div>
    </div>
</div>
