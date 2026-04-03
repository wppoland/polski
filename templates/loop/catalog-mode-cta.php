<?php
/**
 * Loop catalog mode CTA.
 *
 * @var array{mode: string, label: string, url: string, target: string}|null $cta
 * @var WC_Product                                            $product
 * @var string                                                $notice
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="polski-catalog-mode-loop">
    <?php if ($notice !== '') : ?>
        <small class="polski-catalog-mode-loop__notice"><?php echo esc_html($notice); ?></small>
    <?php endif; ?>

    <?php if ($cta !== null) : ?>
        <a
            class="button polski-catalog-mode-loop__button"
            href="<?php echo esc_url($cta['url']); ?>"
            <?php echo $cta['target'] === 'new_tab' ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        >
            <?php echo esc_html($cta['label']); ?>
        </a>
    <?php endif; ?>
</div>
