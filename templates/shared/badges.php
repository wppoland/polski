<?php
/**
 * Product badges.
 *
 * @var list<array{text: string, style: string}> $polski_badges
 * @var string                                   $polski_context
 * @var \WC_Product                              $polski_product
 * @var string                                   $polski_shape
 * @var bool                                     $polski_uppercase
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-badges polski-badges--<?php echo esc_attr($polski_context); ?>">
    <?php foreach ($polski_badges as $polski_badge) : ?>
        <span class="polski-badge polski-badge--<?php echo esc_attr($polski_badge['style']); ?> polski-badge--<?php echo esc_attr($polski_shape); ?><?php echo $polski_uppercase ? ' polski-badge--uppercase' : ''; ?>">
            <?php echo esc_html($polski_badge['text']); ?>
        </span>
    <?php endforeach; ?>
</div>
