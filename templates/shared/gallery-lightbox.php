<?php
/**
 * Gallery lightbox shell.
 *
 * @var array<string, mixed> $polski_settings
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div
    class="polski-gallery-lightbox"
    data-polski-gallery-lightbox
    aria-label="<?php echo esc_attr((string) ($polski_settings['dialog_label'] ?? __('Podgląd galerii produktu', 'polski'))); ?>"
    hidden
>
    <button type="button" class="polski-gallery-lightbox__close" data-polski-gallery-lightbox-close aria-label="<?php echo esc_attr((string) ($polski_settings['close_label'] ?? __('Zamknij podgląd galerii', 'polski'))); ?>">
        ×
    </button>
    <img src="" alt="" data-polski-gallery-lightbox-image />
</div>
