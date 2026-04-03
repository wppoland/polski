<?php
/**
 * Single product wishlist button.
 *
 * @var \Polski\Service\WishlistService $service
 * @var WC_Product                           $product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$button = $service->getButtonData($product);
?>
<button
    type="button"
    class="button polski-wishlist-button<?php echo $button['in_wishlist'] ? ' is-active' : ''; ?>"
    data-polski-wishlist-button
    data-product-id="<?php echo esc_attr((string) $button['product_id']); ?>"
>
    <?php echo esc_html($button['label']); ?>
</button>
