<?php
/**
 * Product loop request a quote link.
 *
 * @var \Polski\Service\QuoteService $service
 * @var WC_Product                        $product
 * @var string                            $button_url
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<a class="button polski-quote-loop-button" href="<?php echo esc_url($button_url); ?>">
    <?php echo esc_html($service->getButtonText($product)); ?>
</a>
