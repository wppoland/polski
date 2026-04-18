<?php
/**
 * Single product shipping costs notice.
 *
 * This template can be overridden by copying it to yourtheme/polski/single-product/shipping-notice.php.
 *
 * @var string $polski_shipping_notice_html The formatted shipping notice HTML.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in TaxDisplayService.
echo $polski_shipping_notice_html;
