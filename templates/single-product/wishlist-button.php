<?php
/**
 * Single product wishlist button.
 *
 * @var \Polski\Service\WishlistService $polski_service
 * @var WC_Product                           $polski_product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_button = $polski_service->getButtonData($polski_product);
?>
<button
    type="button"
    class="button polski-wishlist-button<?php echo $polski_button['in_wishlist'] ? ' is-active' : ''; ?>"
    data-polski-wishlist-button
    data-product-id="<?php echo esc_attr((string) $polski_button['product_id']); ?>"
>
    <?php echo esc_html($polski_button['label']); ?>
</button>
