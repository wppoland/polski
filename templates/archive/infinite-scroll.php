<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\InfiniteScrollService $service
 * @var string                                      $next_page_url
 * @var array<string, mixed>                        $settings
 */
$buttonText = (string) ($settings['button_text'] ?? __('Załaduj więcej produktów', 'polski'));
$showStatus = (bool) ($settings['show_status'] ?? true);
$showButtonInAutoMode = (bool) ($settings['show_button_in_auto_mode'] ?? false);
$mode = (string) ($settings['mode'] ?? 'button');
?>
<div
    class="polski-infinite-scroll"
    data-next-page="<?php echo esc_url($next_page_url); ?>"
    data-button-text="<?php echo esc_attr($buttonText); ?>"
>
    <button type="button" class="button polski-infinite-scroll__button" <?php echo $mode === 'auto' && ! $showButtonInAutoMode ? 'hidden' : ''; ?>>
        <?php echo esc_html($buttonText); ?>
    </button>
    <?php if ($showStatus) : ?>
        <p class="polski-infinite-scroll__status" aria-live="polite"></p>
    <?php endif; ?>
</div>
