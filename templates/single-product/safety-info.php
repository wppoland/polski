<?php
/**
 * Single product safety information (GPSR, safety docs, instructions).
 *
 * @var string      $safety_html The safety information HTML.
 * @var WC_Product  $product     The product object.
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $safety_html;
