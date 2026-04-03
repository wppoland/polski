<?php

declare(strict_types=1);

/**
 * @var \Polski\Service\GiftCardService $service
 * @var list<string>                          $codes
 */
?>
<section class="polski-gift-card-redeem">
    <?php if ((bool) ($service->getSettings()['show_redeem_title'] ?? true)) : ?>
        <h4><?php echo esc_html((string) ($service->getSettings()['redeem_title'] ?? __('Masz kartę podarunkową?', 'polski'))); ?></h4>
    <?php endif; ?>
    <form method="post" class="polski-gift-card-redeem__form">
        <?php wp_nonce_field('polski_gift_card_redeem', 'polski_gift_card_nonce'); ?>
        <input type="hidden" name="polski_gift_card_action" value="apply" />
        <input type="text" name="polski_gift_card_code" placeholder="<?php echo esc_attr((string) ($service->getSettings()['code_placeholder'] ?? __('Wpisz kod karty', 'polski'))); ?>" />
        <button type="submit" class="button"><?php echo esc_html((string) ($service->getSettings()['redeem_button_text'] ?? __('Zastosuj kod', 'polski'))); ?></button>
    </form>

    <?php if ($codes !== []) : ?>
        <ul class="polski-gift-card-redeem__codes">
            <?php foreach ($codes as $code) : ?>
                <li>
                    <span><?php echo esc_html($code); ?></span>
                    <form method="post">
                        <?php wp_nonce_field('polski_gift_card_redeem', 'polski_gift_card_nonce'); ?>
                        <input type="hidden" name="polski_gift_card_action" value="remove" />
                        <input type="hidden" name="polski_gift_card_code" value="<?php echo esc_attr($code); ?>" />
                        <button type="submit" class="button button-link"><?php echo esc_html((string) ($service->getSettings()['remove_button_text'] ?? __('Usuń kod', 'polski'))); ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
