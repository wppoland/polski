<?php

declare(strict_types=1);

defined('ABSPATH') || exit;
/**
 * @var array<string, mixed> $polski_settings
 * @var string               $polski_cta_url
 * @var string               $polski_close_label
 * @var string               $polski_dialog_label
 * @var bool                 $polski_show_cta
 * @var bool                 $polski_show_title
 * @var bool                 $polski_show_close_button
 * @var string               $polski_cta_target
 */
?>
<div class="polski-popup" hidden="hidden" data-polski-popup>
    <div class="polski-popup__backdrop" data-polski-popup-backdrop></div>
    <div class="polski-popup__dialog" role="dialog" aria-modal="true" tabindex="-1" aria-label="<?php echo esc_attr($polski_dialog_label); ?>" <?php echo $polski_show_title ? 'aria-labelledby="polski-popup-title"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <?php if ($polski_show_close_button) : ?>
            <button type="button" class="polski-popup__close" data-polski-popup-close aria-label="<?php echo esc_attr($polski_close_label); ?>">&times;</button>
        <?php endif; ?>
        <?php if ($polski_show_title) : ?>
            <h3 id="polski-popup-title" class="polski-popup__title"><?php echo esc_html((string) ($polski_settings['title'] ?? '')); ?></h3>
        <?php endif; ?>
        <div class="polski-popup__content"><?php echo wp_kses_post(wpautop(wp_kses_post((string) ($polski_settings['content'] ?? '')))); ?></div>
        <?php if ($polski_show_cta) : ?>
            <a
                class="button alt polski-popup__cta"
                href="<?php echo esc_url($polski_cta_url); ?>"
                <?php echo $polski_cta_target === 'new_tab' ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
                <?php echo esc_html((string) ($polski_settings['cta_text'] ?? __('Przejdź dalej', 'polski'))); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
