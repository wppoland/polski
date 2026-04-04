<?php
defined('ABSPATH') || exit;
/**
 * Featured video block.
 *
 * @var string      $video_html
 * @var string      $title
 * @var string      $intro_text
 * @var bool        $show_title
 * @var bool        $show_intro
 * @var \WC_Product $product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-featured-video">
    <?php if ($show_title && $title !== '') : ?>
        <h3 class="polski-featured-video__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($show_intro && $intro_text !== '') : ?>
        <p class="polski-featured-video__intro"><?php echo esc_html($intro_text); ?></p>
    <?php endif; ?>

    <div class="polski-featured-video__embed">
        <?php echo $video_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>
