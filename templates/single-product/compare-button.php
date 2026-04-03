<?php
/**
 * Single product compare button.
 *
 * @var \Polski\Service\CompareService $service
 * @var \WC_Product                         $product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$button = $service->getButtonData($product);
?>
<div class="polski-compare polski-compare--single">
    <button
        type="button"
        class="button polski-compare-button<?php echo $button['in_compare'] ? ' is-active' : ''; ?>"
        data-polski-compare-button
        data-product-id="<?php echo esc_attr((string) $button['product_id']); ?>"
    >
        <?php echo esc_html($button['label']); ?>
    </button>
    <a class="polski-compare-link" href="<?php echo esc_url($button['compare_url']); ?>">
        <?php echo esc_html($service->getCompareLinkText()); ?>
    </a>
</div>
