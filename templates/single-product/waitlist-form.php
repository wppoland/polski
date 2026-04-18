<?php
/**
 * Waitlist form.
 *
 * @var \WC_Product          $polski_product
 * @var array<string, mixed> $polski_settings
 * @var string               $polski_email
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-waitlist" data-polski-waitlist>
    <?php if (! empty($polski_settings['show_title'])) : ?>
        <h3><?php echo esc_html((string) ($polski_settings['title'] ?? '')); ?></h3>
    <?php endif; ?>
    <?php if (! empty($polski_settings['show_intro']) && ! empty($polski_settings['intro_text'])) : ?>
        <p><?php echo esc_html((string) $polski_settings['intro_text']); ?></p>
    <?php endif; ?>
    <form class="polski-waitlist-form">
        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $polski_product->get_id()); ?>" />
        <label>
            <span class="screen-reader-text"><?php echo esc_html((string) ($polski_settings['email_label'] ?? __('Adres email', 'polski'))); ?></span>
            <input type="email" name="email" value="<?php echo esc_attr($polski_email); ?>" placeholder="<?php echo esc_attr((string) ($polski_settings['email_placeholder'] ?? __('Twój adres email', 'polski'))); ?>" required />
        </label>
        <label class="polski-waitlist__privacy">
            <input type="checkbox" name="privacy" value="1" required />
            <span><?php echo esc_html((string) ($polski_settings['privacy_label'] ?? '')); ?></span>
        </label>
        <button type="submit" class="button alt"><?php echo esc_html((string) ($polski_settings['button_text'] ?? '')); ?></button>
        <p class="polski-waitlist__message" data-polski-waitlist-message hidden></p>
    </form>
</div>
