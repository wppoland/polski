<?php
/**
 * Checkout order button text override.
 *
 * This template can be overridden by copying it to yourtheme/spolszczony/checkout/order-button-text.php.
 *
 * @var string $button_text The order button text.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<span class="spolszczony-order-button-text"><?php echo esc_html($button_text); ?></span>
