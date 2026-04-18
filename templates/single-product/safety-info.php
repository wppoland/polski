<?php
/**
 * Single product safety information (GPSR, safety docs, instructions).
 *
 * @var string      $polski_safety_html The safety information HTML.
 * @var WC_Product  $polski_product     The product object.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $polski_safety_html;
