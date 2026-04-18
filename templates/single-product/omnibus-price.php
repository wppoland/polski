<?php
/**
 * Single product Omnibus lowest price display.
 *
 * This template can be overridden by copying it to yourtheme/polski/single-product/omnibus-price.php.
 *
 * @var string      $polski_omnibus_price_html The formatted Omnibus price HTML.
 * @var WC_Product  $polski_product            The product object.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in OmnibusService.
echo $polski_omnibus_price_html;
