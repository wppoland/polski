<?php
/**
 * Loop catalog mode CTA.
 *
 * @var array{mode: string, label: string, url: string}|null $cta
 * @var WC_Product                                            $product
 * @var string                                                $notice
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="spolszczony-catalog-mode-loop">
    <?php if ($notice !== '') : ?>
        <small class="spolszczony-catalog-mode-loop__notice"><?php echo esc_html($notice); ?></small>
    <?php endif; ?>

    <?php if ($cta !== null) : ?>
        <a class="button spolszczony-catalog-mode-loop__button" href="<?php echo esc_url($cta['url']); ?>">
            <?php echo esc_html($cta['label']); ?>
        </a>
    <?php endif; ?>
</div>
