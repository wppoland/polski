<?php
/**
 * Waitlist form.
 *
 * @var \WC_Product          $product
 * @var array<string, mixed> $settings
 * @var string               $email
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<div class="polski-waitlist" data-polski-waitlist>
    <?php if (! empty($settings['show_title'])) : ?>
        <h3><?php echo esc_html((string) ($settings['title'] ?? '')); ?></h3>
    <?php endif; ?>
    <?php if (! empty($settings['show_intro']) && ! empty($settings['intro_text'])) : ?>
        <p><?php echo esc_html((string) $settings['intro_text']); ?></p>
    <?php endif; ?>
    <form class="polski-waitlist-form">
        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product->get_id()); ?>" />
        <label>
            <span class="screen-reader-text"><?php echo esc_html((string) ($settings['email_label'] ?? __('Adres email', 'polski'))); ?></span>
            <input type="email" name="email" value="<?php echo esc_attr($email); ?>" placeholder="<?php echo esc_attr((string) ($settings['email_placeholder'] ?? __('Twój adres email', 'polski'))); ?>" required />
        </label>
        <label class="polski-waitlist__privacy">
            <input type="checkbox" name="privacy" value="1" required />
            <span><?php echo esc_html((string) ($settings['privacy_label'] ?? '')); ?></span>
        </label>
        <button type="submit" class="button alt"><?php echo esc_html((string) ($settings['button_text'] ?? '')); ?></button>
        <p class="polski-waitlist__message" data-polski-waitlist-message hidden></p>
    </form>
</div>
