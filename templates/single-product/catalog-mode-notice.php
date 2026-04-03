<?php
/**
 * Single product catalog mode notice.
 *
 * @var WC_Product                         $product
 * @var string                             $notice
 * @var array{mode: string, label: string, url: string, target: string}|null $cta
 * @var bool                               $hide_price
 * @var bool                               $hide_cart
 * @var string                             $restriction_text
 * @var bool                               $show_cta
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="woocommerce-info polski-catalog-mode-notice">
    <?php if ($notice !== '' && ! empty($show_notice ?? true)) : ?>
        <p><?php echo esc_html($notice); ?></p>
    <?php endif; ?>

    <?php if (($hide_price || $hide_cart) && ! empty($show_restriction_text ?? true)) : ?>
        <p>
            <?php echo esc_html($restriction_text); ?>
        </p>
    <?php endif; ?>

    <?php if ($show_cta && $cta !== null) : ?>
        <p>
            <a
                class="button alt"
                href="<?php echo esc_url($cta['url']); ?>"
                <?php echo $cta['target'] === 'new_tab' ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            >
                <?php echo esc_html($cta['label']); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
