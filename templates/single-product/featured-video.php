<?php
/**
 * Featured video block.
 *
 * @var string      $polski_video_html
 * @var string      $polski_title
 * @var string      $polski_intro_text
 * @var bool        $polski_show_title
 * @var bool        $polski_show_intro
 * @var \WC_Product $polski_product
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-featured-video">
    <?php if ($polski_show_title && $polski_title !== '') : ?>
        <h3 class="polski-featured-video__title"><?php echo esc_html($polski_title); ?></h3>
    <?php endif; ?>

    <?php if ($polski_show_intro && $polski_intro_text !== '') : ?>
        <p class="polski-featured-video__intro"><?php echo esc_html($polski_intro_text); ?></p>
    <?php endif; ?>

    <div class="polski-featured-video__embed">
        <?php echo $polski_video_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>
