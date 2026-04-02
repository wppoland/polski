<?php
/**
 * Single product Omnibus lowest price display.
 *
 * This template can be overridden by copying it to yourtheme/spolszczony/single-product/omnibus-price.php.
 *
 * @var string      $omnibus_price_html The formatted Omnibus price HTML.
 * @var WC_Product  $product            The product object.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in OmnibusService.
echo $omnibus_price_html;
