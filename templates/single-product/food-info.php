<?php
/**
 * Single product food information (nutrients, allergens, ingredients, Nutri-Score).
 *
 * @var string      $food_info_html The formatted food info HTML.
 * @var WC_Product  $product        The product object.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $food_info_html;
