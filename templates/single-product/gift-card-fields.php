<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\GiftCardService $service
 * @var \WC_Product                           $product
 * @var list<float>                           $amounts
 */
?>
<div class="polski-gift-card-fields">
    <?php if ((bool) ($service->getSettings()['show_product_form_title'] ?? true)) : ?>
        <h4><?php echo esc_html((string) ($service->getSettings()['product_form_title'] ?? __('Dane karty podarunkowej', 'polski'))); ?></h4>
    <?php endif; ?>
    <p class="form-row form-row-first">
        <label for="polski_gift_card_recipient_name"><?php echo esc_html((string) ($service->getSettings()['recipient_name_label'] ?? __('Imię odbiorcy', 'polski'))); ?></label>
        <input type="text" name="polski_gift_card_recipient_name" id="polski_gift_card_recipient_name" required="required" />
    </p>
    <p class="form-row form-row-last">
        <label for="polski_gift_card_recipient_email"><?php echo esc_html((string) ($service->getSettings()['recipient_email_label'] ?? __('Email odbiorcy', 'polski'))); ?></label>
        <input type="email" name="polski_gift_card_recipient_email" id="polski_gift_card_recipient_email" required="required" />
    </p>
    <p class="form-row form-row-first">
        <label for="polski_gift_card_sender_name"><?php echo esc_html((string) ($service->getSettings()['sender_name_label'] ?? __('Imię nadawcy', 'polski'))); ?></label>
        <input type="text" name="polski_gift_card_sender_name" id="polski_gift_card_sender_name" required="required" />
    </p>
    <p class="form-row form-row-last">
        <label for="polski_gift_card_amount"><?php echo esc_html((string) ($service->getSettings()['amount_label'] ?? __('Kwota', 'polski'))); ?></label>
        <select name="polski_gift_card_amount" id="polski_gift_card_amount">
            <?php foreach ($amounts as $amount) : ?>
                <option value="<?php echo esc_attr((string) $amount); ?>"><?php echo esc_html(strip_tags(wc_price($amount))); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php if ($product->get_meta('_polski_gift_card_allow_custom_amount', true) === 'yes' || ($service->getSettings()['allow_custom_amount'] ?? true)) : ?>
        <p class="form-row form-row-first">
            <label for="polski_gift_card_custom_amount"><?php echo esc_html((string) ($service->getSettings()['custom_amount_label'] ?? __('Własna kwota', 'polski'))); ?></label>
            <input type="number" step="0.01" min="0" name="polski_gift_card_custom_amount" id="polski_gift_card_custom_amount" />
        </p>
    <?php endif; ?>
    <p class="form-row form-row-wide">
        <label for="polski_gift_card_message"><?php echo esc_html((string) ($service->getSettings()['message_label'] ?? __('Wiadomość', 'polski'))); ?></label>
        <textarea name="polski_gift_card_message" id="polski_gift_card_message" rows="3"></textarea>
    </p>
</div>
