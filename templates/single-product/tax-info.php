<?php
/**
 * Single product VAT/tax info notice.
 *
 * This template can be overridden by copying it to yourtheme/polski/single-product/tax-info.php.
 *
 * @var string      $tax_info_html The formatted tax info HTML.
 * @var WC_Product  $product       The product object.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is pre-escaped in TaxDisplayService.
echo $tax_info_html;
