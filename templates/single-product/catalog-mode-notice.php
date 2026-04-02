<?php
/**
 * Single product catalog mode notice.
 *
 * @var WC_Product                         $product
 * @var string                             $notice
 * @var array{mode: string, label: string, url: string}|null $cta
 * @var bool                               $hide_price
 * @var bool                               $hide_cart
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="woocommerce-info spolszczony-catalog-mode-notice">
    <?php if ($notice !== '') : ?>
        <p><?php echo esc_html($notice); ?></p>
    <?php endif; ?>

    <?php if ($hide_price || $hide_cart) : ?>
        <p>
            <?php
            echo esc_html(
                $hide_price && $hide_cart
                    ? __('Ceny i zakup online są ukryte dla tego produktu.', 'spolszczony')
                    : ($hide_price ? __('Cena jest ukryta dla tego produktu.', 'spolszczony') : __('Zakup online jest wyłączony dla tego produktu.', 'spolszczony')),
            );
            ?>
        </p>
    <?php endif; ?>

    <?php if ($cta !== null) : ?>
        <p>
            <a class="button alt" href="<?php echo esc_url($cta['url']); ?>">
                <?php echo esc_html($cta['label']); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
