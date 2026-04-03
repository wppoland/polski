<?php
/**
 * Pre-order product notice.
 *
 * @var string      $notice
 * @var string      $title
 * @var bool        $show_title
 * @var \WC_Product $product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="polski-preorder-notice woocommerce-info">
    <?php if ($show_title) : ?>
        <strong class="polski-preorder-notice__title"><?php echo esc_html($title); ?></strong>
        <br />
    <?php endif; ?>
    <?php echo esc_html($notice); ?>
</div>
