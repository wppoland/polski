<?php
/**
 * Single product compare button.
 *
 * @var \Polski\Service\CompareService $polski_service
 * @var \WC_Product                         $polski_product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
$polski_button = $polski_service->getButtonData($polski_product);
?>
<div class="polski-compare polski-compare--single">
    <button
        type="button"
        class="button polski-compare-button<?php echo $polski_button['in_compare'] ? ' is-active' : ''; ?>"
        data-polski-compare-button
        data-product-id="<?php echo esc_attr((string) $polski_button['product_id']); ?>"
        aria-pressed="<?php echo $polski_button['in_compare'] ? 'true' : 'false'; ?>"
    >
        <?php echo esc_html($polski_button['label']); ?>
    </button>
    <a class="polski-compare-link" href="<?php echo esc_url($polski_button['compare_url']); ?>">
        <?php echo esc_html($polski_service->getCompareLinkText()); ?>
    </a>
</div>
