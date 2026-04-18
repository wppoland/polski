<?php
/**
 * Single product food information (nutrients, allergens, ingredients, Nutri-Score).
 *
 * @var string      $polski_food_info_html The formatted food info HTML.
 * @var WC_Product  $polski_product        The product object.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $polski_food_info_html;
