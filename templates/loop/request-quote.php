<?php
/**
 * Product loop request a quote link.
 *
 * @var \Spolszczony\Service\QuoteService $service
 * @var WC_Product                        $product
 * @var string                            $button_url
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<a class="button spolszczony-quote-loop-button" href="<?php echo esc_url($button_url); ?>">
    <?php echo esc_html($service->getButtonText($product)); ?>
</a>
