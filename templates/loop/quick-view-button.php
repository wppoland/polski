<?php
/**
 * Loop quick view button.
 *
 * @var \Polski\Service\QuickViewService $service
 * @var \WC_Product                           $product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="polski-quick-view-trigger">
    <button
        type="button"
        class="button polski-quick-view-button"
        data-polski-quick-view
        data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>"
    >
        <?php echo esc_html($service->getButtonText()); ?>
    </button>
</div>
