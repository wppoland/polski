<?php
/**
 * Single product request a quote button and modal.
 *
 * @var \Polski\Service\QuoteService $service
 * @var array<string, mixed>              $settings
 * @var string                            $privacy_label
 * @var WC_Product                        $product
 * @var bool                              $success
 * @var string                            $error
 *
 * @package Polski/Templates
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$allowGuest = (bool) ($settings['allow_guest'] ?? true);
$isLoggedIn = is_user_logged_in();
$buttonText = $service->getButtonText($product);
$minimumQuantity = $service->getMinimumQuantity($product);
$autoOpen = isset($_GET['polski_quote']) || $error !== '';
$successMessage = sanitize_text_field((string) wp_unslash($_GET['polski_quote_message'] ?? ''));
$nameLabel = (string) ($settings['name_label'] ?? __('Imię i nazwisko', 'polski'));
$emailLabel = (string) ($settings['email_label'] ?? __('Email', 'polski'));
$phoneLabel = (string) ($settings['phone_label'] ?? __('Telefon', 'polski'));
$companyLabel = (string) ($settings['company_label'] ?? __('Firma', 'polski'));
$nipLabel = (string) ($settings['nip_label'] ?? __('NIP', 'polski'));
$postcodeLabel = (string) ($settings['postcode_label'] ?? __('Kod pocztowy', 'polski'));
$quantityLabel = (string) ($settings['quantity_label'] ?? __('Ilość', 'polski'));
$messageLabel = (string) ($settings['message_label'] ?? __('Szczegóły zapytania', 'polski'));
$messagePlaceholder = (string) ($settings['message_placeholder'] ?? __('Napisz czego potrzebujesz, jaki masz termin i jakie są założenia zamówienia.', 'polski'));
?>
<div
    class="polski-quote"
    data-polski-quote-root
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
        <a class="button alt polski-quote__button" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
            <?php echo esc_html($buttonText); ?>
        </a>
        <p class="polski-quote__help">
            <?php echo esc_html((string) ($settings['login_required_text'] ?? __('Zaloguj się, aby wysłać zapytanie ofertowe dla tego produktu.', 'polski'))); ?>
        </p>
    <?php else : ?>
        <button type="button" class="button alt polski-quote__button" data-polski-quote-open>
            <?php echo esc_html($buttonText); ?>
        </button>

        <div class="polski-quote__modal" data-polski-quote-modal hidden>
            <div class="polski-quote__backdrop" data-polski-quote-close></div>
            <div class="polski-quote__panel" role="dialog" aria-modal="true" aria-labelledby="polski-quote-title-<?php echo esc_attr((string) $product->get_id()); ?>">
                <button type="button" class="polski-quote__close" data-polski-quote-close aria-label="<?php echo esc_attr((string) ($settings['close_label'] ?? __('Zamknij formularz wyceny', 'polski'))); ?>">
                    &times;
                </button>

                <h3 id="polski-quote-title-<?php echo esc_attr((string) $product->get_id()); ?>" class="polski-quote__title">
                    <?php echo esc_html((string) ($settings['modal_title'] ?? '')); ?>
                </h3>

                <?php if (! empty($settings['intro_text'])) : ?>
                    <p class="polski-quote__intro">
                        <?php echo esc_html((string) $settings['intro_text']); ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="polski-quote__form">
                    <input type="hidden" name="action" value="polski_submit_quote_request">
                    <input type="hidden" name="_polski_quote_nonce" value="<?php echo esc_attr(wp_create_nonce('polski_quote_request')); ?>">
                    <input type="hidden" name="polski_quote_product_id" value="<?php echo esc_attr((string) $product->get_id()); ?>">
                    <input type="hidden" name="polski_quote_variation_id" value="" data-polski-quote-variation>
                    <input type="hidden" name="polski_quote_source_url" value="<?php echo esc_url(get_permalink($product->get_id()) ?: ''); ?>">

                    <div class="polski-quote__grid">
                        <label class="polski-quote__field">
                            <span><?php echo esc_html($nameLabel); ?></span>
                            <input type="text" name="polski_quote_name" required>
                        </label>

                        <label class="polski-quote__field">
                            <span><?php echo esc_html($emailLabel); ?></span>
                            <input type="email" name="polski_quote_email" required>
                        </label>

                        <label class="polski-quote__field">
                            <span><?php echo esc_html($phoneLabel); ?></span>
                            <input type="text" name="polski_quote_phone" <?php echo ! empty($settings['require_phone']) ? 'required' : ''; ?>>
                        </label>

                        <label class="polski-quote__field">
                            <span><?php echo esc_html($companyLabel); ?></span>
                            <input type="text" name="polski_quote_company" <?php echo ! empty($settings['require_company']) ? 'required' : ''; ?>>
                        </label>

                        <label class="polski-quote__field">
                            <span><?php echo esc_html($nipLabel); ?></span>
                            <input type="text" name="polski_quote_nip" inputmode="numeric" <?php echo ! empty($settings['require_nip']) ? 'required' : ''; ?>>
                        </label>

                        <label class="polski-quote__field">
                            <span><?php echo esc_html($postcodeLabel); ?></span>
                            <input type="text" name="polski_quote_postcode" <?php echo ! empty($settings['require_postcode']) ? 'required' : ''; ?>>
                        </label>

                        <label class="polski-quote__field">
                            <span><?php echo esc_html($quantityLabel); ?></span>
                            <input type="number" name="polski_quote_quantity" min="<?php echo esc_attr($minimumQuantity); ?>" step="0.001" value="<?php echo esc_attr($minimumQuantity); ?>" required>
                        </label>
                    </div>

                    <label class="polski-quote__field polski-quote__field--full">
                        <span><?php echo esc_html($messageLabel); ?></span>
                        <textarea name="polski_quote_message" rows="4" placeholder="<?php echo esc_attr($messagePlaceholder); ?>"></textarea>
                    </label>

                    <?php if (! empty($settings['privacy_required'])) : ?>
                        <label class="polski-quote__consent">
                            <input type="checkbox" name="polski_quote_privacy" value="1" required>
                            <span><?php echo wp_kses_post($privacy_label); ?></span>
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="button alt polski-quote__submit">
                        <?php echo esc_html((string) ($settings['submit_text'] ?? '')); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
