<?php
/**
 * Single product unit price display.
 *
 * This template can be overridden by copying it to yourtheme/spolszczony/single-product/unit-price.php.
 *
 * @var string      $unit_price_html The formatted unit price HTML.
 * @var WC_Product  $product         The product object.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in PriceDisplayService.
echo $unit_price_html;
