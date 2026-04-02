<?php
/**
 * Single product shipping costs notice.
 *
 * This template can be overridden by copying it to yourtheme/spolszczony/single-product/shipping-notice.php.
 *
 * @var string $shipping_notice_html The formatted shipping notice HTML.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in TaxDisplayService.
echo $shipping_notice_html;
