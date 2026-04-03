<?php
/**
 * Product badges.
 *
 * @var list<array{text: string, style: string}> $badges
 * @var string                                   $context
 * @var \WC_Product                              $product
 * @var string                                   $shape
 * @var bool                                     $uppercase
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;
?>
<div class="polski-badges polski-badges--<?php echo esc_attr($context); ?>">
    <?php foreach ($badges as $badge) : ?>
        <span class="polski-badge polski-badge--<?php echo esc_attr($badge['style']); ?> polski-badge--<?php echo esc_attr($shape); ?><?php echo $uppercase ? ' polski-badge--uppercase' : ''; ?>">
            <?php echo esc_html($badge['text']); ?>
        </span>
    <?php endforeach; ?>
</div>
