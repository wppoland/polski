<?php
/**
 * Single product request a quote button and modal.
 *
 * @var \Spolszczony\Service\QuoteService $service
 * @var array<string, mixed>              $settings
 * @var string                            $privacy_label
 * @var WC_Product                        $product
 * @var bool                              $success
 * @var string                            $error
 *
 * @package Spolszczony/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$allowGuest = (bool) ($settings['allow_guest'] ?? true);
$isLoggedIn = is_user_logged_in();
$buttonText = $service->getButtonText($product);
$minimumQuantity = $service->getMinimumQuantity($product);
$autoOpen = isset($_GET['spolszczony_quote']) || $error !== '';
$successMessage = sanitize_text_field((string) wp_unslash($_GET['spolszczony_quote_message'] ?? ''));
?>
<div
    class="spolszczony-quote"
    data-spolszczony-quote-root
    data-auto-open="<?php echo $autoOpen ? '1' : '0'; ?>"
>
    <?php if ($success) : ?>
        <div class="woocommerce-message" role="status">
            <?php echo esc_html($successMessage !== '' ? $successMessage : (string) ($settings['success_text'] ?? '')); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="woocommerce-error" role="alert">
            <?php echo esc_html($error); ?>
        </div>
    <?php endif; ?>

    <?php if (! $allowGuest && ! $isLoggedIn) : ?>
        <a class="button alt spolszczony-quote__button" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
            <?php echo esc_html($buttonText); ?>
        </a>
        <p class="spolszczony-quote__help">
            <?php echo esc_html__('Zaloguj się, aby wysłać zapytanie ofertowe dla tego produktu.', 'spolszczony'); ?>
        </p>
    <?php else : ?>
        <button type="button" class="button alt spolszczony-quote__button" data-spolszczony-quote-open>
            <?php echo esc_html($buttonText); ?>
        </button>

        <div class="spolszczony-quote__modal" data-spolszczony-quote-modal hidden>
            <div class="spolszczony-quote__backdrop" data-spolszczony-quote-close></div>
            <div class="spolszczony-quote__panel" role="dialog" aria-modal="true" aria-labelledby="spolszczony-quote-title-<?php echo esc_attr((string) $product->get_id()); ?>">
                <button type="button" class="spolszczony-quote__close" data-spolszczony-quote-close aria-label="<?php echo esc_attr__('Close', 'spolszczony'); ?>">
                    &times;
                </button>

                <h3 id="spolszczony-quote-title-<?php echo esc_attr((string) $product->get_id()); ?>" class="spolszczony-quote__title">
                    <?php echo esc_html((string) ($settings['modal_title'] ?? '')); ?>
                </h3>

                <?php if (! empty($settings['intro_text'])) : ?>
                    <p class="spolszczony-quote__intro">
                        <?php echo esc_html((string) $settings['intro_text']); ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="spolszczony-quote__form">
                    <input type="hidden" name="action" value="spolszczony_submit_quote_request">
                    <input type="hidden" name="_spolszczony_quote_nonce" value="<?php echo esc_attr(wp_create_nonce('spolszczony_quote_request')); ?>">
                    <input type="hidden" name="spolszczony_quote_product_id" value="<?php echo esc_attr((string) $product->get_id()); ?>">
                    <input type="hidden" name="spolszczony_quote_variation_id" value="" data-spolszczony-quote-variation>
                    <input type="hidden" name="spolszczony_quote_source_url" value="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>">

                    <div class="spolszczony-quote__grid">
                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('Imię i nazwisko', 'spolszczony'); ?></span>
                            <input type="text" name="spolszczony_quote_name" required>
                        </label>

                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('Email', 'spolszczony'); ?></span>
                            <input type="email" name="spolszczony_quote_email" required>
                        </label>

                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('Telefon', 'spolszczony'); ?></span>
                            <input type="text" name="spolszczony_quote_phone" <?php echo ! empty($settings['require_phone']) ? 'required' : ''; ?>>
                        </label>

                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('Firma', 'spolszczony'); ?></span>
                            <input type="text" name="spolszczony_quote_company" <?php echo ! empty($settings['require_company']) ? 'required' : ''; ?>>
                        </label>

                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('NIP', 'spolszczony'); ?></span>
                            <input type="text" name="spolszczony_quote_nip" inputmode="numeric" <?php echo ! empty($settings['require_nip']) ? 'required' : ''; ?>>
                        </label>

                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('Kod pocztowy', 'spolszczony'); ?></span>
                            <input type="text" name="spolszczony_quote_postcode" <?php echo ! empty($settings['require_postcode']) ? 'required' : ''; ?>>
                        </label>

                        <label class="spolszczony-quote__field">
                            <span><?php echo esc_html__('Ilość', 'spolszczony'); ?></span>
                            <input type="number" name="spolszczony_quote_quantity" min="<?php echo esc_attr($minimumQuantity); ?>" step="0.001" value="<?php echo esc_attr($minimumQuantity); ?>" required>
                        </label>
                    </div>

                    <label class="spolszczony-quote__field spolszczony-quote__field--full">
                        <span><?php echo esc_html__('Szczegóły zapytania', 'spolszczony'); ?></span>
                        <textarea name="spolszczony_quote_message" rows="4" placeholder="<?php echo esc_attr__('Napisz czego potrzebujesz, jaki masz termin i jakie są założenia zamówienia.', 'spolszczony'); ?>"></textarea>
                    </label>

                    <?php if (! empty($settings['privacy_required'])) : ?>
                        <label class="spolszczony-quote__consent">
                            <input type="checkbox" name="spolszczony_quote_privacy" value="1" required>
                            <span><?php echo wp_kses_post($privacy_label); ?></span>
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="button alt spolszczony-quote__submit">
                        <?php echo esc_html((string) ($settings['submit_text'] ?? '')); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
