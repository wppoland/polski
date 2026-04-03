<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $settings
 * @var string               $cta_url
 * @var string               $close_label
 * @var string               $dialog_label
 * @var bool                 $show_cta
 * @var bool                 $show_title
 * @var bool                 $show_close_button
 * @var string               $cta_target
 */
?>
<div class="polski-popup" hidden="hidden" data-polski-popup>
    <div class="polski-popup__backdrop" data-polski-popup-backdrop></div>
    <div class="polski-popup__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($dialog_label); ?>" <?php echo $show_title ? 'aria-labelledby="polski-popup-title"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <?php if ($show_close_button) : ?>
            <button type="button" class="polski-popup__close" data-polski-popup-close aria-label="<?php echo esc_attr($close_label); ?>">&times;</button>
        <?php endif; ?>
        <?php if ($show_title) : ?>
            <h3 id="polski-popup-title" class="polski-popup__title"><?php echo esc_html((string) ($settings['title'] ?? '')); ?></h3>
        <?php endif; ?>
        <div class="polski-popup__content"><?php echo wpautop(wp_kses_post((string) ($settings['content'] ?? ''))); ?></div>
        <?php if ($show_cta) : ?>
            <a
                class="button alt polski-popup__cta"
                href="<?php echo esc_url($cta_url); ?>"
                <?php echo $cta_target === 'new_tab' ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
                <?php echo esc_html((string) ($settings['cta_text'] ?? __('Przejdź dalej', 'polski'))); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
