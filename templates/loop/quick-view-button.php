<?php
/**
 * Loop quick view button.
 *
 * @var \Polski\Service\QuickViewService $polski_service
 * @var \WC_Product                           $polski_product
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
        data-product-id="<?php echo esc_attr((string) $polski_product->get_id()); ?>"
    >
        <?php echo esc_html($polski_service->getButtonText()); ?>
    </button>
</div>
