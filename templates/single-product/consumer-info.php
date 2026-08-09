<?php
/**
 * Single product consumer information (Directive (EU) 2024/825).
 *
 * @var string      $polski_consumer_html The consumer information HTML.
 * @var WC_Product  $polski_product       The product object.
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $polski_consumer_html;
