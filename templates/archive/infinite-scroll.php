<?php

declare(strict_types=1);

defined('ABSPATH') || exit;
/**
 * @var \Polski\Service\InfiniteScrollService $polski_service
 * @var string                                      $polski_next_page_url
 * @var array<string, mixed>                        $polski_settings
 */
$polski_buttonText = (string) ($polski_settings['button_text'] ?? __('Załaduj więcej produktów', 'polski'));
$polski_showStatus = (bool) ($polski_settings['show_status'] ?? true);
$polski_showButtonInAutoMode = (bool) ($polski_settings['show_button_in_auto_mode'] ?? false);
$polski_mode = (string) ($polski_settings['mode'] ?? 'button');
?>
<div
    class="polski-infinite-scroll"
    data-next-page="<?php echo esc_url($polski_next_page_url); ?>"
    data-button-text="<?php echo esc_attr($polski_buttonText); ?>"
>
    <button type="button" class="button polski-infinite-scroll__button" <?php echo $polski_mode === 'auto' && ! $polski_showButtonInAutoMode ? 'hidden' : ''; ?>>
        <?php echo esc_html($polski_buttonText); ?>
    </button>
    <?php if ($polski_showStatus) : ?>
        <p class="polski-infinite-scroll__status" aria-live="polite"></p>
    <?php endif; ?>
</div>
